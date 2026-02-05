<?php include 'includes/header.php'; ?>

<main class="contact-page">
    <section class="contact-hero">
        <div class="contact-hero-inner">
            <div class="contact-hero-eyebrow">Contact</div>
            <h1>We’re here to help</h1>
            <p>Questions, feedback, partnerships, or support — send us a message and we’ll respond as soon as possible.</p>
            <div class="contact-hero-actions">
                <a class="btn-primary" href="jobs.php">Explore Jobs</a>
                <a class="btn-secondary contact-hero-secondary" href="#contact-form">Send a message</a>
            </div>
        </div>
    </section>

    <section class="contact-methods">
        <article class="contact-method-card">
            <div class="contact-method-icon"><i class="fa-regular fa-envelope"></i></div>
            <div>
                <h3>Email</h3>
                <p class="muted-text">support@careernest.com</p>
            </div>
        </article>
        <article class="contact-method-card">
            <div class="contact-method-icon"><i class="fa-solid fa-phone"></i></div>
            <div>
                <h3>Phone</h3>
                <p class="muted-text">+94 11 123 4567</p>
            </div>
        </article>
        <article class="contact-method-card">
            <div class="contact-method-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div>
                <h3>Office</h3>
                <p class="muted-text">Colombo, Sri Lanka</p>
            </div>
        </article>
    </section>

    <section class="contact-grid" id="contact-form">
        <div class="contact-card contact-card--form">
            <div class="contact-card-header">
                <h2>Send a message</h2>
                <p>Share a few details so we can direct you to the right team.</p>
            </div>
            <form class="auth-form" action="" method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="Your name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="How can we help?" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" placeholder="Share a few details so we can assist you better." required></textarea>
                </div>
                <button class="btn-primary" type="submit">Submit</button>
            </form>
            <p class="muted-text">This demo form illustrates the final UI and layout.</p>
        </div>

        <div class="contact-card contact-info">
            <div class="contact-card-header">
                <h2>Platform details</h2>
                <p>For account issues, hiring partnerships, or press inquiries, our team is ready to help.</p>
            </div>
            <div class="contact-meta">
                <p><span class="contact-meta-icon"><i class="fa-regular fa-envelope"></i></span><strong>Email</strong> <span class="contact-meta-value">support@careernest.com</span></p>
                <p><span class="contact-meta-icon"><i class="fa-solid fa-phone"></i></span><strong>Phone</strong> <span class="contact-meta-value">+94 11 123 4567</span></p>
                <p><span class="contact-meta-icon"><i class="fa-solid fa-location-dot"></i></span><strong>Office</strong> <span class="contact-meta-value">Colombo, Sri Lanka</span></p>
                <p><span class="contact-meta-icon"><i class="fa-regular fa-clock"></i></span><strong>Hours</strong> <span class="contact-meta-value">Mon–Fri, 9:00–18:00</span></p>
            </div>
            <div class="contact-note">
                <h3>Quick links</h3>
                <div class="contact-links">
                    <a class="btn-secondary" href="about.php">About</a>
                    <a class="btn-secondary" href="news.php">News</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
