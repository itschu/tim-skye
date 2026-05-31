<?php

/**
 * Privacy Policy Page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

$page_title = __('Privacy Policy');
require_once ROOT . '/includes/public-header.php';
?>

<main class="legal-page">
    <div class="legal-container reveal">
        <div class="legal-header">
            <h1 class="section-h"><?php echo $page_title; ?></h1>
            <p><?php echo __('We value your privacy and are committed to protecting your personal data.'); ?></p>
            <div class="legal-divider"></div>
        </div>

        <div class="legal-body">

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('Information We Collect'); ?>
                </h3>
                <p><?php echo __('We collect information to provide better services to all our users. The types of information we collect include:'); ?></p>
                <ul>
                    <li><strong><?php echo __('Personal Information:'); ?></strong> <?php echo __('Name, email address, phone number, and payment details when you register.'); ?></li>
                    <li><strong><?php echo __('Usage Data:'); ?></strong> <?php echo __('Information on how you use our website, including pages visited and time spent.'); ?></li>
                    <li><strong><?php echo __('Device Information:'); ?></strong> <?php echo __('IP address, browser type, and operating system.'); ?></li>
                </ul>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('How We Use Information'); ?>
                </h3>
                <p><?php echo __('We use the information we collect to operate and maintain our services, notify you about changes to our service, allow you to participate in interactive features, provide customer support, and detect, prevent and address technical issues.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('Data Security'); ?>
                </h3>
                <p><?php echo __('The security of your data is important to us, but remember that no method of transmission over the Internet, or method of electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your Personal Data, we cannot guarantee its absolute security.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('Cookies'); ?>
                </h3>
                <p><?php echo __('We use cookies and similar tracking technologies to track the activity on our Service and hold certain information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.'); ?></p>
            </section>

            <div style="border-top: 0.5px solid var(--border); padding-top: 2rem; margin-top: 2rem;">
                <p style="text-align: center; font-size: 0.82rem; color: var(--muted);">
                    <?php echo __('Last updated:'); ?> <?php echo date("F j, Y"); ?>
                </p>
            </div>

        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
