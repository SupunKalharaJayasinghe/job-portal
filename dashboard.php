
<?php include 'includes/header.php'; ?>

<main class="dashboard-page">
    <section class="welcome-section">
        <h1>Welcome, [User]</h1>
        <p>Manage your activity and keep track of your jobs in one place.</p>
    </section>

    <section class="employer-section">
        <div class="section-header">
            <h2>Your Posted Jobs</h2>
            <button class="btn-primary" type="button">Post New Job</button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Frontend Developer</td>
                        <td>Engineering</td>
                        <td>Colombo</td>
                        <td>Open</td>
                    </tr>
                    <tr>
                        <td>UI/UX Designer</td>
                        <td>Design</td>
                        <td>Remote</td>
                        <td>Draft</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="seeker-section">
        <div class="section-header">
            <h2>Jobs You Applied For</h2>
            <button class="btn-secondary" type="button">Upload Resume</button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Product Designer</td>
                        <td>Nimbus Labs</td>
                        <td>Remote</td>
                        <td>In Review</td>
                    </tr>
                    <tr>
                        <td>Marketing Specialist</td>
                        <td>Atlas Commerce</td>
                        <td>Kandy</td>
                        <td>Submitted</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
