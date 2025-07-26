<?php
// Contact Us page
?>

<div class="contact-page">
    <div class="contact-header">
        <h1>Contact Us</h1>
        <p>Get in touch with us for any inquiries or support</p>
    </div>
    
    <div class="contact-grid">
        <div class="contact-info">
            <div class="info-card">
                <h3>📍 Visit Us</h3>
                <p>Birzeit University</p>
                <p>Birzeit, Palestine</p>
                <p>P.O. Box 14, Birzeit</p>
            </div>
            
            <div class="info-card">
                <h3>📞 Call Us</h3>
                <p>Tel: +970-2-298-2000</p>
                <p>Fax: +970-2-298-2001</p>
                <p>Mobile: +970-59-999-9999</p>
            </div>
            
            <div class="info-card">
                <h3>✉️ Email Us</h3>
                <p>General Inquiries: info@birzeitflat.com</p>
                <p>Support: support@birzeitflat.com</p>
                <p>Business: business@birzeitflat.com</p>
            </div>
            
            <div class="info-card">
                <h3>⏰ Business Hours</h3>
                <p>Monday - Thursday: 8:00 AM - 4:00 PM</p>
                <p>Friday: 8:00 AM - 1:00 PM</p>
                <p>Saturday - Sunday: Closed</p>
            </div>
        </div>
        
        <div class="contact-form">
            <h2>Send Us a Message</h2>
            <form method="POST" action="main.php?page=contact">
                <div class="form-group">
                    <label for="name" class="required">Your Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="required">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="subject" class="required">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message" class="required">Message</label>
                    <textarea id="message" name="message" rows="5" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
    
    
    </div>
</div> 