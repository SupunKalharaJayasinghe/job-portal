
<?php include 'includes/header.php'; ?>

<main class="auth-page">
    <section class="form-section">
        <div class="section-header">
            <h1>Create Your Account</h1>
            <p>Register as a job seeker or employer to get started.</p>
        </div>
        <form class="auth-form" action="#" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <div class="form-group">
                <label for="user_type">I am a...</label>
                <select id="user_type" name="user_type" required>
                    <option value="" disabled selected>Select an option</option>
                    <option value="seeker">Job Seeker</option>
                    <option value="employer">Employer</option>
                </select>
            </div>
            <button class="btn-primary" type="submit">Register</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
