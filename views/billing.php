<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Nexus-IaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <div class="col-md-10 p-4">
                <h2 class="mb-4">Billing & Balance</h2>

                <!-- Balance Summary -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Current Balance</h6>
                                <h2 class="text-success mb-0">$<?= number_format($balance, 2) ?></h2>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Hourly Cost</h6>
                                <h2 class="text-warning mb-0">$<?= number_format($billingSummary['hourly_cost'], 2) ?></h2>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Hours Remaining</h6>
                                <h2 class="text-info mb-0"><?= number_format($billingSummary['hours_remaining'], 1) ?>h</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Transaction History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Balance After</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No transactions yet
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $tx): ?>
                                            <?php 
                                            $details = json_decode($tx['details'], true);
                                            $amount = $details['amount'] ?? 0;
                                            $description = $details['description'] ?? 'N/A';
                                            $newBalance = $details['new_balance'] ?? 0;
                                            $isCredit = $tx['action'] === 'balance_added';
                                            ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i:s', strtotime($tx['timestamp'])) ?></td>
                                                <td>
                                                    <span class="badge <?= $isCredit ? 'bg-success' : 'bg-danger' ?>">
                                                        <?= $isCredit ? 'Credit' : 'Debit' ?>
                                                    </span>
                                                </td>
                                                <td class="<?= $isCredit ? 'text-success' : 'text-danger' ?>">
                                                    <?= $isCredit ? '+' : '-' ?>$<?= number_format($amount, 2) ?>
                                                </td>
                                                <td><?= htmlspecialchars($description) ?></td>
                                                <td>$<?= number_format($newBalance, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
