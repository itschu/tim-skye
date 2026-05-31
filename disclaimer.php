<?php

/**
 * Risk Disclaimer Page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

$page_title = __('Risk Disclaimer');
require_once ROOT . '/includes/public-header.php';
?>

<main class="legal-page">
    <div class="legal-container reveal">
        <div class="legal-header">
            <h1 class="section-h"><?php echo $page_title; ?></h1>
            <p><?php echo __('Important information regarding investment risks.'); ?></p>
            <div class="legal-divider"></div>
        </div>

        <div class="legal-body">

            <div class="highlight-box" style="background: rgba(224, 85, 85, 0.08); border-color: rgba(224, 85, 85, 0.3);">
                <h4 style="color: var(--red); margin-bottom: 0.5rem;"><?php echo __('High Risk Warning'); ?></h4>
                <p style="color: #e8a0a0; font-size: 0.88rem; line-height: 1.7; margin-bottom: 0;"><?php echo __('Trading and investing carries a high level of risk and may not be suitable for all investors. You could lose some or all of your initial investment.'); ?></p>
            </div>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('General Advice Warning'); ?>
                </h3>
                <p><?php echo __('The information provided on this website is for general information purposes only and does not constitute financial advice, investment advice, trading advice, or any other sort of advice. You should not treat any of the website\'s content as such. We do not recommend that any asset should be bought, sold, or held by you. Do conduct your own due diligence and consult your financial advisor before making any investment decisions.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('Volatility Risks'); ?>
                </h3>
                <p><?php echo __('The values of investments can fluctuate significantly due to market conditions. Past performance is not indicative of future results. The value of your investment can go down as well as up, and you may not get back the amount you invested.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('No Guarantees'); ?>
                </h3>
                <p><?php echo __('While we strive to ensure the accuracy of the information contained on this website, we do not guarantee the accuracy, reliability, or completeness of any information. We are not responsible for any errors or omissions or for the results obtained from the use of such information.'); ?></p>
            </section>

            <section>
                <h3>
                    <span class="icon-gold">&#9670;</span> <?php echo __('User Responsibility'); ?>
                </h3>
                <p><?php echo __('You acknowledge and agree that you are fully responsible for your own investment decisions and that you are aware of the risks associated with investing. You agree to hold the platform and its operators harmless from any and all losses, liabilities, or damages resulting from your investment activities.'); ?></p>
            </section>

        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
