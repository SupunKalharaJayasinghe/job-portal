
<?php include 'includes/header.php'; ?>

<main class="post-job-page">
    <section class="form-section">
        <div class="section-header">
            <h1>Post a New Job</h1>
            <p>Share the details of your open role with job seekers.</p>
        </div>
        <form class="job-form" action="#" method="post">
            <div class="form-group">
                <label for="job_title">Job Title</label>
                <input type="text" id="job_title" name="job_title" placeholder="e.g. Backend Engineer" required>
            </div>
            <div class="form-group">
                <label for="job_description">Job Description</label>
                <textarea id="job_description" name="job_description" rows="6" placeholder="Describe the role and responsibilities" required></textarea>
            </div>
            <div class="form-group">
                <label for="job_category">Category</label>
                <input type="text" id="job_category" name="job_category" placeholder="e.g. Engineering" required>
            </div>
            <div class="form-group">
                <label for="job_location">Location</label>
                <input type="text" id="job_location" name="job_location" placeholder="City or Remote" required>
            </div>
            <div class="form-group">
                <label for="job_salary">Salary Range</label>
                <input type="text" id="job_salary" name="job_salary" placeholder="e.g. LKR 150,000 - 200,000" required>
            </div>
            <button class="btn-primary" type="submit">Publish Job</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
