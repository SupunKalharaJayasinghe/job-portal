
<?php include 'includes/header.php'; ?>

<main class="auth-page">
    <section class="form-section">
        <div class="section-header">
            <h1>Welcome Back</h1>
            <p>Log in to access your dashboard.</p>
        </div>
        <form class="auth-form" action="#" method="post">
            <div class="form-group">
                <label for="login_email">Email</label>
                <input type="email" id="login_email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <input type="password" id="login_password" name="password" placeholder="Enter your password" required>
            </div>
            <button class="btn-primary" type="submit">Login</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
