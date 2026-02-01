<?php include 'includes/header.php'; ?>

<main class="contact-page">
    <section class="about-hero">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you. Share your questions, feedback, or partnership ideas.</p>
    </section>

    <section class="section-header">
        <h2>Get in Touch</h2>
        <p>Fill out the form and our team will respond as soon as possible.</p>
    </section>

    <section class="contact-grid">
        <div class="contact-card">
            <h3>Send a Message</h3>
            <form class="auth-form" action="" method="post">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Your name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="How can we help?" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" placeholder="Share a few details so we can assist you better." required></textarea>
                </div>
                <button class="btn-primary" type="submit">Submit</button>
            </form>
            <p class="muted-text">Note: This demo form does not send real emails, but illustrates the final UI and layout.</p>
        </div>

        <div class="contact-card contact-info">
            <h3>Platform Details</h3>
            <div class="contact-meta">
                <p><strong>Email:</strong> support@careernest.com</p>
                <p><strong>Phone:</strong> +94 11 123 4567</p>
                <p><strong>Office:</strong> Colombo, Sri Lanka</p>
            </div>
            <p>For account issues, hiring partnerships, or press inquiries, our team is ready to help.</p>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
