<?php
define('ROOT', __DIR__);
require_once 'includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

$page_title = __('Contact Us');
// SEO
$page_description = get_setting('contact_page_description', get_setting('site_description', __('We are here to help you 24/7')));
$page_keywords = get_setting('contact_page_keywords', get_setting('site_keywords', 'contact,support,customer service'));
require_once 'includes/public-header.php';
?>

<main>
    <!-- HERO -->
    <section id="hero" style="min-height:50vh;">
        <div class="hero-bg-img"></div>
        <div class="hero-overlay"></div>
        <div class="hero-grid-bg"></div>
        <div class="hero-glow"></div>

        <div class="hero-left" style="z-index:2; position:relative;">
            <div class="eyebrow fu"><?php echo __('Get In Touch'); ?></div>
            <h1 class="fu d1">
                <?php echo __('Let\'s talk'); ?> <em><?php echo __('strategy'); ?></em>
            </h1>
            <p class="hero-sub fu d2"><?php echo __('Have questions about a package or want a custom arrangement? Our team responds within 24 hours.'); ?></p>
        </div>
    </section>

    <!-- CONTACT -->
    <section id="contact">
        <div class="contact-left">
            <div class="section-tag"><?php echo __('Contact Information'); ?></div>
            <h2 class="section-h"><?php echo __('Reach out'); ?> <em><?php echo __('anytime'); ?></em></h2>
            <p class="section-lead"><?php echo __('We are here to help you 24/7. Choose the method that works best for you.'); ?></p>

            <div class="cdet">
                <div class="cdet-icon">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M2 4l6 5 6-5M2 4h12v8H2V4z" /></svg>
                </div>
                <div class="cdet-txt"><strong><?php echo __('Email'); ?></strong><span><a href="mailto:<?php echo e(get_setting('contact_email', 'support@example.com')); ?>" style="color:var(--muted);text-decoration:none;"><?php echo e(get_setting('contact_email', 'support@example.com')); ?></a></span></div>
            </div>

            <div class="cdet">
                <div class="cdet-icon">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M13 10.5c-.7-.5-1.8-.3-2.3.3l-.6.8c-1.3-.7-2.4-1.8-3.1-3.1l.8-.6c.6-.5.8-1.6.3-2.3L7 3.8C6.5 3 5.4 2.8 4.7 3.4L3.6 4.5C3.2 5 3 5.7 3.1 6.4 3.5 9.4 6.5 12.4 9.6 12.9c.7.1 1.4-.1 1.9-.5l1.1-1.1c.6-.7.4-1.8-.6-2.8z" /></svg>
                </div>
                <div class="cdet-txt"><strong><?php echo __('Phone'); ?></strong><span><a href="tel:<?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?>" style="color:var(--muted);text-decoration:none;"><?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?></a></span></div>
            </div>

            <div class="cdet">
                <div class="cdet-icon">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="8" cy="6" r="3" /><path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg>
                </div>
                <div class="cdet-txt"><strong><?php echo __('Address'); ?></strong><span><?php echo nl2br(e(get_setting('contact_address', "123 Business Avenue, Suite 100\nFinancial District, NY 10001"))); ?></span></div>
            </div>

            <?php
            $contact_social_setting = get_setting('social_links', '');
            $contact_social = [];
            if ($contact_social_setting) {
                $decoded = json_decode($contact_social_setting, true);
                if (is_array($decoded)) $contact_social = $decoded;
            }
            $contact_social_non_empty = array_filter($contact_social, fn($v) => !empty($v));
            ?>
            <?php if (!empty($contact_social_non_empty)): ?>
                <div style="margin-top:2.5rem; border-top:0.5px solid var(--border); padding-top:1.5rem;">
                    <p style="font-size:0.62rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--muted); margin-bottom:0.75rem;"><?php echo __('Follow Us'); ?></p>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <?php foreach ($contact_social as $key => $url): ?>
                            <?php if (!empty($url)): ?>
                                <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer" style="font-size:0.75rem; color:var(--muted-light); text-decoration:none; border:0.5px solid var(--border); padding:0.4rem 0.8rem; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--gold)'; this.style.color='var(--gold-light)'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--muted-light)'"><?php echo ucfirst(e($key)); ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <form class="contact-form-wrap" action="/actions/contact-submit.php" method="POST">
            <div class="frow">
                <div class="ff">
                    <label><?php echo __('Name'); ?></label>
                    <input type="text" name="name" placeholder="<?php echo __('John Doe'); ?>" required />
                </div>
                <div class="ff">
                    <label><?php echo __('Email'); ?></label>
                    <input type="email" name="email" placeholder="<?php echo __('john@example.com'); ?>" required />
                </div>
            </div>
            <div class="ff">
                <label><?php echo __('Subject'); ?></label>
                <input type="text" name="subject" placeholder="<?php echo __('How can we help?'); ?>" required />
            </div>
            <div class="ff">
                <label><?php echo __('Message'); ?></label>
                <textarea name="message" rows="5" placeholder="<?php echo __('Tell us about your inquiry...'); ?>" required></textarea>
            </div>
            <button type="submit" class="fsub"><?php echo __('Send Message'); ?> →</button>
        </form>
    </section>
</main>

<?php require_once 'includes/public-footer.php'; ?>
