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

<style>
    p {
        font-size: 0.9rem;
    }
</style>

<main class="pt-24 pb-16">
    <div class="max-w-5xl mx-auto px-6 lg:px-8">
        <div class="relative">
            <div class="absolute -top-20 -right-20 w-64 h-64 bg-neon-purple/10 blur-[80px] rounded-full -z-10 pointer-events-none animate-blob"></div>
            <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-neon-cyan/10 blur-[80px] rounded-full -z-10 pointer-events-none animate-blob animation-delay-2000"></div>

            <div class="text-center mb-16" data-aos="fade-down">
                <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-4"><?php echo $page_title; ?></h1>
                <p class="text-gray-400"><?php echo __('Please read these terms carefully before using our platform.'); ?></p>
                <div class="w-24 h-1.5 bg-gradient-to-r from-neon-purple to-neon-cyan mx-auto rounded-full mt-8"></div>
            </div>

            <div class="glass-panel p-6 md:p-12 rounded-3xl border border-gray-800 text-gray-300 leading-relaxed space-y-10" data-aos="fade-up">

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <span class="text-neon-purple">01.</span> <?php echo __('Acceptance of Terms'); ?>
                    </h3>
                    <p><?php echo __('By accessing and using this website, you accept and agree to be bound by the terms and provision of this agreement. In addition, when using this websites particular services, you shall be subject to any posted guidelines or rules applicable to such services.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <span class="text-neon-purple">02.</span> <?php echo __('Use License'); ?>
                    </h3>
                    <p class="mb-4"><?php echo __('Permission is granted to temporarily download one copy of the materials (information or software) on the website for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:'); ?></p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-400 marker:text-neon-cyan">
                        <li><?php echo __('modify or copy the materials;'); ?></li>
                        <li><?php echo __('use the materials for any commercial purpose, or for any public display (commercial or non-commercial);'); ?></li>
                        <li><?php echo __('attempt to decompile or reverse engineer any software contained on the website;'); ?></li>
                        <li><?php echo __('remove any copyright or other proprietary notations from the materials;'); ?></li>
                    </ul>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <span class="text-neon-purple">03.</span> <?php echo __('Disclaimer'); ?>
                    </h3>
                    <p><?php echo __('The materials on the website are provided "as is". We make no warranties, expressed or implied, and hereby disclaim and negate all other warranties, including without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <span class="text-neon-purple">04.</span> <?php echo __('Limitations'); ?>
                    </h3>
                    <p><?php echo __('In no event shall we or our suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on the website.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <span class="text-neon-purple">05.</span> <?php echo __('Governing Law'); ?>
                    </h3>
                    <p><?php echo __('Any claim relating to the website shall be governed by the laws of the jurisdiction of the company headquarters without regard to its conflict of law provisions.'); ?></p>
                </section>

                <div class="bg-dark-900/50 p-6 rounded-2xl border border-gray-700 mt-8 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 class="text-white font-bold mb-1"><?php echo __('Questions about the Terms?'); ?></h4>
                        <p class="text-sm text-gray-500"><?php echo __('Our support team is available to clarify any points.'); ?></p>
                    </div>
                    <a href="/contact" class="px-6 py-2 bg-gray-800 hover:bg-neon-purple text-white rounded-lg transition-colors text-sm font-bold">
                        <?php echo __('Contact Support'); ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>