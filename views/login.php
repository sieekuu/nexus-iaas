<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(Database::getConfig('APP_NAME', 'Nexus-IaaS')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-dark text-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card bg-dark border-secondary shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">Nexus-IaaS</h2>
                            <p class="text-muted">Cloud Infrastructure Management</p>
                        </div>

                        <?php
                        $error = '';
                        $success = '';

                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $email = $_POST['email'] ?? '';
                            $password = $_POST['password'] ?? '';

                            $result = Auth::login($email, $password);
                            
                            if ($result['success']) {
                                header('Location: /dashboard.php');
                                exit;
                            } else {
                                $error = $result['message'];
                            }
                        }
                        ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="/login.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control bg-dark text-light border-secondary" 
                                       id="email" name="email" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control bg-dark text-light border-secondary" 
                                       id="password" name="password" required>
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <p class="text-muted">
                                Don't have an account? 
                                <a href="/register.php" class="text-primary text-decoration-none">Register</a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 text-muted small">
                    <p>&copy; 2026 Krzysztof Siek. Licensed under MIT License.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
