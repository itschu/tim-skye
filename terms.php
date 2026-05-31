<?php

/**
 * Terms of Service Page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

$page_title = __('Terms of Service');
require_once ROOT . '/includes/public-header.php';
?>

<main class="legal-page">
    <div class="legal-container reveal">
        <div class="legal-header">
            <h1 class="section-h"><?php echo $page_title; ?></h1>
            <p><?php echo __('Please read these terms carefully before using our platform.'); ?></p>
            <div class="legal-divider"></div>
        </div>

        <div class="legal-body">

            <section>
                <h3>
                    <span class="icon-gold">01.</span> <?php echo __('Acceptance of Terms'); ?>
                </h3>
                <p><?php echo __('By accessing and using this website, you accept and agree to be bound by the terms and provision of this agreement. In addition, when using this websites particular services, you shall be subject to any posted guidelines or rules applicable to such services.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">02.</span> <?php echo __('Use License'); ?>
                </h3>
                <p><?php echo __('Permission is granted to temporarily download one copy of the materials (information or software) on the website for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:'); ?></p>
                <ul>
                    <li><?php echo __('modify or copy the materials;'); ?></li>
                    <li><?php echo __('use the materials for any commercial purpose, or for any public display (commercial or non-commercial);'); ?></li>
                    <li><?php echo __('attempt to decompile or reverse engineer any software contained on the website;'); ?></li>
                    <li><?php echo __('remove any copyright or other proprietary notations from the materials;'); ?></li>
                </ul>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">03.</span> <?php echo __('Disclaimer'); ?>
                </h3>
                <p><?php echo __('The materials on the website are provided "as is". We make no warranties, expressed or implied, and hereby disclaim and negate all other warranties, including without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">04.</span> <?php echo __('Limitations'); ?>
                </h3>
                <p><?php echo __('In no event shall we or our suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on the website.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">05.</span> <?php echo __('Governing Law'); ?>
                </h3>
                <p><?php echo __('Any claim relating to the website shall be governed by the laws of the jurisdiction of the company headquarters without regard to its conflict of law provisions.'); ?></p>
            </section>

            <div style="border-top: 0.5px solid var(--border); padding-top: 2.5rem; margin-top: 2.5rem;">
                <h4 style="font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; font-weight: 300; color: var(--txt); margin-bottom: 0.5rem;"><?php echo __('Questions about the Terms?'); ?></h4>
                <p style="margin-bottom: 1.2rem;"><?php echo __('Our support team is available to clarify any points.'); ?></p>
                <a href="/contact" class="btn-gold"><?php echo __('Contact Support'); ?></a>
            </div>

        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
