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

<style>
    p {
        font-size: 0.9rem;
    }
</style>

<main class="pt-24 pb-16">
    <div class="max-w-5xl mx-auto px-6 lg:px-8">
        <div class="relative">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-red-600/5 blur-[100px] rounded-full -z-10 pointer-events-none animate-blob"></div>
            <div class="absolute -top-20 -right-20 w-64 h-64 bg-orange-500/10 blur-[80px] rounded-full -z-10 pointer-events-none animate-blob animation-delay-2000"></div>

            <div class="text-center mb-16" data-aos="fade-down">
                <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-4"><?php echo $page_title; ?></h1>
                <p class="text-gray-400"><?php echo __('Important information regarding investment risks.'); ?></p>
                <div class="w-24 h-1.5 bg-gradient-to-r from-red-500 to-orange-500 mx-auto rounded-full mt-8"></div>
            </div>

            <div class="glass-panel p-4 md:p-12 rounded-3xl border border-gray-800 text-gray-300 leading-relaxed space-y-10" data-aos="fade-up">

                <div class="bg-red-900/20 border border-red-500/30 p-6 rounded-2xl flex items-start gap-2 flex-col">
                    <div class="flex w-full items-center gap-4">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl mt-1 shrink-0"></i>
                        <h4 class="text-red-400 font-bold"><?php echo __('High Risk Warning'); ?></h4>
                    </div>

                    <p class="text-sm text-red-200/80"><?php echo __('Trading and investing carries a high level of risk and may not be suitable for all investors. You could lose some or all of your initial investment.'); ?></p>
                </div>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4"><?php echo __('General Advice Warning'); ?></h3>
                    <p><?php echo __('The information provided on this website is for general information purposes only and does not constitute financial advice, investment advice, trading advice, or any other sort of advice. You should not treat any of the website\'s content as such. We do not recommend that any asset should be bought, sold, or held by you. Do conduct your own due diligence and consult your financial advisor before making any investment decisions.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4"><?php echo __('Volatility Risks'); ?></h3>
                    <p><?php echo __('The values of investments can fluctuate significantly due to market conditions. Past performance is not indicative of future results. The value of your investment can go down as well as up, and you may not get back the amount you invested.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4"><?php echo __('No Guarantees'); ?></h3>
                    <p><?php echo __('While we strive to ensure the accuracy of the information contained on this website, we do not guarantee the accuracy, reliability, or completeness of any information. We are not responsible for any errors or omissions or for the results obtained from the use of such information.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4"><?php echo __('User Responsibility'); ?></h3>
                    <p><?php echo __('You acknowledge and agree that you are fully responsible for your own investment decisions and that you are aware of the risks associated with investing. You agree to hold the platform and its operators harmless from any and all losses, liabilities, or damages resulting from your investment activities.'); ?></p>
                </section>

            </div>
        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>