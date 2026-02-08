<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Nexus-IaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <div class="col-md-10 p-4">
                <h2 class="mb-4">Settings</h2>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Account Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Account Type</label>
                                    <input type="text" class="form-control" value="<?= $user['is_admin'] ? 'Administrator' : 'User' ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" value="<?= date('F j, Y', $user['login_time']) ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="/settings.php">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::getCsrfToken() ?>">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required minlength="8">
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </form>
                            </div>
                        </div>

                        <!-- API Access (Future Feature) -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">API Access</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">API key management coming soon...</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Quick Stats -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Stats</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $stats = Instance::getUserStats($user['id']);
                                ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Instances:</span>
                                    <strong><?= $stats['total'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Running:</span>
                                    <strong class="text-success"><?= $stats['running'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Stopped:</span>
                                    <strong class="text-muted"><?= $stats['stopped'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total vCPU:</span>
                                    <strong><?= $stats['total_vcpu'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total RAM:</span>
                                    <strong><?= number_format($stats['total_ram'] / 1024, 1) ?> GB</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">Danger Zone</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">
                                    Deleting your account will permanently remove all your data, including instances and billing history.
                                </p>
                                <button class="btn btn-outline-danger w-100" disabled>
                                    <i class="bi bi-exclamation-triangle"></i> Delete Account
                                </button>
                                <small class="text-muted d-block mt-2">
                                    Contact support to delete your account
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
