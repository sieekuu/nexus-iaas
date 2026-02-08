#!/usr/bin/env python3
"""
Nexus-IaaS Proxmox Bridge
Copyright (c) 2026 Krzysztof Siek
Licensed under the MIT License.

This script acts as a bridge between the PHP application and Proxmox VE API.
It receives commands via CLI arguments and performs operations on Proxmox.
"""

import os
import sys
import json
import argparse
import logging
from typing import Dict, Any, Optional
from proxmoxer import ProxmoxAPI
from proxmoxer.core import ResourceException

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class ProxmoxBridge:
    """Proxmox API Bridge for infrastructure operations"""
    
    def __init__(self, host: str, user: str, password: str, node: str, verify_ssl: bool = False):
        """
        Initialize Proxmox connection
        
        Args:
            host: Proxmox host IP/domain
            user: Proxmox user (e.g., root@pam)
            password: Proxmox password
            node: Default Proxmox node name
            verify_ssl: Whether to verify SSL certificates
        """
        self.node = node
        
        try:
            self.proxmox = ProxmoxAPI(
                host,
                user=user,
                password=password,
                verify_ssl=verify_ssl
            )
            logger.info(f"Connected to Proxmox at {host}")
        except Exception as e:
            logger.error(f"Failed to connect to Proxmox: {str(e)}")
            raise
    
    def create_vm(self, vmid: int, name: str, vcpu: int, ram: int, disk: int, 
                  os_template: str, ip_address: str, gateway: str) -> Dict[str, Any]:
        """
        Create a new virtual machine
        
        Args:
            vmid: VM ID number
            name: VM name
            vcpu: Number of CPU cores
            ram: RAM in MB
            disk: Disk size in GB
            os_template: OS template name
            ip_address: Static IP address
            gateway: Network gateway
            
        Returns:
            Dictionary with operation result
        """
        try:
            logger.info(f"Creating VM {vmid} ({name})")
            
            # Create VM configuration
            config = {
                'vmid': vmid,
                'name': name,
                'cores': vcpu,
                'memory': ram,
                'net0': f'virtio,bridge=vmbr0,firewall=1',
                'ostype': 'l26',  # Linux 2.6+ kernel
                'bootdisk': 'scsi0',
                'scsihw': 'virtio-scsi-pci',
                'scsi0': f'local-lvm:{disk}',
                'ide2': f'local:iso/{os_template}.iso,media=cdrom',
                'description': f'Created by Nexus-IaaS\nIP: {ip_address}\nGateway: {gateway}'
            }
            
            # Create the VM
            task_id = self.proxmox.nodes(self.node).qemu.create(**config)
            
            # Wait for task to complete (with timeout)
            status = self._wait_for_task(task_id)
            
            if status == 'OK':
                logger.info(f"VM {vmid} created successfully")
                return {
                    'success': True,
                    'message': f'VM {name} created successfully',
                    'vmid': vmid,
                    'task_id': task_id
                }
            else:
                raise Exception(f"VM creation failed with status: {status}")
                
        except ResourceException as e:
            logger.error(f"Proxmox API error creating VM: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error creating VM: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def start_vm(self, vmid: int) -> Dict[str, Any]:
        """
        Start a virtual machine
        
        Args:
            vmid: VM ID number
            
        Returns:
            Dictionary with operation result
        """
        try:
            logger.info(f"Starting VM {vmid}")
            
            # Check current status
            status = self.proxmox.nodes(self.node).qemu(vmid).status.current.get()
            
            if status['status'] == 'running':
                return {
                    'success': True,
                    'message': 'VM is already running',
                    'vmid': vmid
                }
            
            # Start the VM
            task_id = self.proxmox.nodes(self.node).qemu(vmid).status.start.post()
            
            logger.info(f"VM {vmid} started successfully")
            return {
                'success': True,
                'message': 'VM started successfully',
                'vmid': vmid,
                'task_id': task_id
            }
            
        except ResourceException as e:
            logger.error(f"Proxmox API error starting VM: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error starting VM: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def stop_vm(self, vmid: int, force: bool = False) -> Dict[str, Any]:
        """
        Stop a virtual machine
        
        Args:
            vmid: VM ID number
            force: Force shutdown if True
            
        Returns:
            Dictionary with operation result
        """
        try:
            logger.info(f"Stopping VM {vmid} (force={force})")
            
            # Check current status
            status = self.proxmox.nodes(self.node).qemu(vmid).status.current.get()
            
            if status['status'] == 'stopped':
                return {
                    'success': True,
                    'message': 'VM is already stopped',
                    'vmid': vmid
                }
            
            # Stop the VM (shutdown or stop)
            if force:
                task_id = self.proxmox.nodes(self.node).qemu(vmid).status.stop.post()
            else:
                task_id = self.proxmox.nodes(self.node).qemu(vmid).status.shutdown.post()
            
            logger.info(f"VM {vmid} stopped successfully")
            return {
                'success': True,
                'message': 'VM stopped successfully',
                'vmid': vmid,
                'task_id': task_id
            }
            
        except ResourceException as e:
            logger.error(f"Proxmox API error stopping VM: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error stopping VM: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def reboot_vm(self, vmid: int) -> Dict[str, Any]:
        """
        Reboot a virtual machine
        
        Args:
            vmid: VM ID number
            
        Returns:
            Dictionary with operation result
        """
        try:
            logger.info(f"Rebooting VM {vmid}")
            
            # Check current status
            status = self.proxmox.nodes(self.node).qemu(vmid).status.current.get()
            
            if status['status'] != 'running':
                return {
                    'success': False,
                    'message': 'VM must be running to reboot',
                    'vmid': vmid
                }
            
            # Reboot the VM
            task_id = self.proxmox.nodes(self.node).qemu(vmid).status.reboot.post()
            
            logger.info(f"VM {vmid} rebooted successfully")
            return {
                'success': True,
                'message': 'VM rebooted successfully',
                'vmid': vmid,
                'task_id': task_id
            }
            
        except ResourceException as e:
            logger.error(f"Proxmox API error rebooting VM: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error rebooting VM: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def delete_vm(self, vmid: int, purge: bool = True) -> Dict[str, Any]:
        """
        Delete a virtual machine
        
        Args:
            vmid: VM ID number
            purge: Remove from backup jobs if True
            
        Returns:
            Dictionary with operation result
        """
        try:
            logger.info(f"Deleting VM {vmid}")
            
            # Stop VM if running
            status = self.proxmox.nodes(self.node).qemu(vmid).status.current.get()
            if status['status'] == 'running':
                logger.info(f"VM {vmid} is running, stopping first...")
                self.stop_vm(vmid, force=True)
                import time
                time.sleep(3)  # Wait for VM to stop
            
            # Delete the VM
            task_id = self.proxmox.nodes(self.node).qemu(vmid).delete(purge=int(purge))
            
            logger.info(f"VM {vmid} deleted successfully")
            return {
                'success': True,
                'message': 'VM deleted successfully',
                'vmid': vmid,
                'task_id': task_id
            }
            
        except ResourceException as e:
            logger.error(f"Proxmox API error deleting VM: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error deleting VM: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def get_vm_status(self, vmid: int) -> Dict[str, Any]:
        """
        Get VM status
        
        Args:
            vmid: VM ID number
            
        Returns:
            Dictionary with VM status
        """
        try:
            status = self.proxmox.nodes(self.node).qemu(vmid).status.current.get()
            
            return {
                'success': True,
                'vmid': vmid,
                'status': status['status'],
                'uptime': status.get('uptime', 0),
                'cpu': status.get('cpu', 0),
                'mem': status.get('mem', 0),
                'maxmem': status.get('maxmem', 0)
            }
            
        except ResourceException as e:
            return {
                'success': False,
                'message': f'VM not found or API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def get_console_url(self, vmid: int) -> Dict[str, Any]:
        """
        Get noVNC console URL for a VM
        
        Args:
            vmid: VM ID number
            
        Returns:
            Dictionary with console URL
        """
        try:
            logger.info(f"Generating console URL for VM {vmid}")
            
            # Check VM status
            status = self.proxmox.nodes(self.node).qemu(vmid).status.current.get()
            
            if status['status'] != 'running':
                return {
                    'success': False,
                    'message': 'VM must be running to access console',
                    'vmid': vmid
                }
            
            # Generate VNC proxy ticket
            vnc_data = self.proxmox.nodes(self.node).qemu(vmid).vncproxy.post()
            
            if not vnc_data:
                raise Exception("Failed to generate VNC proxy")
            
            # Construct noVNC URL
            # Format: https://proxmox:8006/?console=kvm&novnc=1&vmid=<vmid>&node=<node>&resize=scale&ticket=<ticket>&port=<port>
            ticket = vnc_data.get('ticket', '')
            port = vnc_data.get('port', 5900)
            
            # Note: In production, you'd construct a full URL with your Proxmox host
            console_url = f"https://{self.proxmox.host}:8006/?console=kvm&novnc=1&vmid={vmid}&node={self.node}&resize=scale&ticket={ticket}&port={port}"
            
            logger.info(f"Console URL generated for VM {vmid}")
            return {
                'success': True,
                'message': 'Console URL generated',
                'vmid': vmid,
                'console_url': console_url,
                'ticket': ticket,
                'port': port
            }
            
        except ResourceException as e:
            logger.error(f"Proxmox API error generating console: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error generating console: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def list_snapshots(self, vmid: int) -> Dict[str, Any]:
        """
        List snapshots for a VM
        
        Args:
            vmid: VM ID number
            
        Returns:
            Dictionary with snapshots list
        """
        try:
            logger.info(f"Listing snapshots for VM {vmid}")
            
            # Get snapshots from Proxmox
            snapshots = self.proxmox.nodes(self.node).qemu(vmid).snapshot.get()
            
            # Filter out 'current' pseudo-snapshot
            snapshot_list = [s for s in snapshots if s.get('name') != 'current']
            
            logger.info(f"Found {len(snapshot_list)} snapshots for VM {vmid}")
            return {
                'success': True,
                'message': f'Found {len(snapshot_list)} snapshots',
                'vmid': vmid,
                'snapshots': snapshot_list
            }
            
        except ResourceException as e:
            logger.error(f"Proxmox API error listing snapshots: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error listing snapshots: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def create_snapshot(self, vmid: int, snapshot_name: str, description: str = '') -> Dict[str, Any]:
        """
        Create a snapshot for a VM
        
        Args:
            vmid: VM ID number
            snapshot_name: Name for the snapshot
            description: Optional description
            
        Returns:
            Dictionary with operation result
        """
        try:
            logger.info(f"Creating snapshot '{snapshot_name}' for VM {vmid}")
            
            # Create snapshot
            task_id = self.proxmox.nodes(self.node).qemu(vmid).snapshot.post(
                snapname=snapshot_name,
                description=description or f'Created by Nexus-IaaS',
                vmstate=0  # Don't include RAM state (faster)
            )
            
            # Wait for task
            status = self._wait_for_task(task_id, timeout=120)
            
            if status == 'OK':
                logger.info(f"Snapshot '{snapshot_name}' created for VM {vmid}")
                return {
                    'success': True,
                    'message': f'Snapshot "{snapshot_name}" created successfully',
                    'vmid': vmid,
                    'snapshot_name': snapshot_name,
                    'task_id': task_id
                }
            else:
                raise Exception(f"Snapshot creation failed with status: {status}")
            
        except ResourceException as e:
            logger.error(f"Proxmox API error creating snapshot: {str(e)}")
            return {
                'success': False,
                'message': f'Proxmox API error: {str(e)}',
                'error': str(e)
            }
        except Exception as e:
            logger.error(f"Error creating snapshot: {str(e)}")
            return {
                'success': False,
                'message': f'Error: {str(e)}',
                'error': str(e)
            }
    
    def _wait_for_task(self, task_id: str, timeout: int = 60) -> str:
        """
        Wait for a Proxmox task to complete
        
        Args:
            task_id: Task UPID
            timeout: Maximum wait time in seconds
            
        Returns:
            Task status string
        """
        import time
        elapsed = 0
        
        while elapsed < timeout:
            try:
                status = self.proxmox.nodes(self.node).tasks(task_id).status.get()
                if status.get('status') == 'stopped':
                    return status.get('exitstatus', 'unknown')
            except:
                pass
            
            time.sleep(2)
            elapsed += 2
        
        return 'timeout'


def main():
    """Main entry point"""
    parser = argparse.ArgumentParser(description='Nexus-IaaS Proxmox Bridge')
    parser.add_argument('--action', required=True, 
                        choices=['create', 'start', 'stop', 'delete', 'status', 'reboot', 'console', 'snapshot_list', 'snapshot_create'],
                        help='Action to perform')
    parser.add_argument('--vmid', type=int, required=True, help='VM ID')
    parser.add_argument('--name', help='VM name (for create)')
    parser.add_argument('--vcpu', type=int, help='Number of CPU cores (for create)')
    parser.add_argument('--ram', type=int, help='RAM in MB (for create)')
    parser.add_argument('--disk', type=int, help='Disk size in GB (for create)')
    parser.add_argument('--os-template', help='OS template (for create)')
    parser.add_argument('--ip-address', help='IP address (for create)')
    parser.add_argument('--gateway', help='Gateway (for create)')
    parser.add_argument('--node', help='Proxmox node name')
    parser.add_argument('--force', action='store_true', help='Force operation')
    parser.add_argument('--snapshot-name', help='Snapshot name (for snapshot_create)')
    parser.add_argument('--snapshot-description', help='Snapshot description (for snapshot_create)', default='')
    
    args = parser.parse_args()
    
    # Load configuration from environment
    host = os.getenv('PROXMOX_HOST')
    user = os.getenv('PROXMOX_USER')
    password = os.getenv('PROXMOX_PASSWORD')
    node = args.node or os.getenv('PROXMOX_NODE', 'pve')
    verify_ssl = os.getenv('PROXMOX_VERIFY_SSL', 'false').lower() == 'true'
    
    if not all([host, user, password]):
        result = {
            'success': False,
            'message': 'Missing Proxmox configuration. Check .env file.',
            'error': 'Missing environment variables'
        }
        print(json.dumps(result))
        sys.exit(1)
    
    try:
        bridge = ProxmoxBridge(host, user, password, node, verify_ssl)
        
        # Execute action
        if args.action == 'create':
            if not all([args.name, args.vcpu, args.ram, args.disk, args.os_template, args.ip_address, args.gateway]):
                result = {'success': False, 'message': 'Missing required arguments for create action'}
            else:
                result = bridge.create_vm(
                    args.vmid, args.name, args.vcpu, args.ram, args.disk,
                    args.os_template, args.ip_address, args.gateway
                )
        
        elif args.action == 'start':
            result = bridge.start_vm(args.vmid)
        
        elif args.action == 'stop':
            result = bridge.stop_vm(args.vmid, args.force)
        
        elif args.action == 'reboot':
            result = bridge.reboot_vm(args.vmid)
        
        elif args.action == 'delete':
            result = bridge.delete_vm(args.vmid)
        
        elif args.action == 'status':
            result = bridge.get_vm_status(args.vmid)
        
        elif args.action == 'console':
            result = bridge.get_console_url(args.vmid)
        
        elif args.action == 'snapshot_list':
            result = bridge.list_snapshots(args.vmid)
        
        elif args.action == 'snapshot_create':
            if not args.snapshot_name:
                result = {'success': False, 'message': 'Missing --snapshot-name for snapshot_create action'}
            else:
                result = bridge.create_snapshot(args.vmid, args.snapshot_name, args.snapshot_description)
        
        else:
            result = {'success': False, 'message': 'Unknown action'}
        
        # Output JSON result (for PHP to parse)
        print(json.dumps(result))
        sys.exit(0 if result.get('success', False) else 1)
        
    except Exception as e:
        result = {
            'success': False,
            'message': f'Bridge error: {str(e)}',
            'error': str(e)
        }
        print(json.dumps(result))
        logger.exception("Fatal error")
        sys.exit(1)


if __name__ == '__main__':
    main()
