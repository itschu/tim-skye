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

<style>
    p {
        font-size: 0.9rem;
    }
</style>

<main class="pt-24 pb-16">
    <div class="max-w-5xl mx-auto px-6 lg:px-8">
        <div class="relative">
            <div class="absolute -top-20 -left-20 w-64 h-64 bg-neon-cyan/10 blur-[80px] rounded-full -z-10 pointer-events-none animate-blob"></div>
            <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-neon-purple/10 blur-[80px] rounded-full -z-10 pointer-events-none animate-blob animation-delay-2000"></div>

            <div class="text-center mb-16" data-aos="fade-down">
                <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-4"><?php echo $page_title; ?></h1>
                <p class="text-gray-400"><?php echo __('We value your privacy and are committed to protecting your personal data.'); ?></p>
                <div class="w-24 h-1.5 bg-gradient-to-r from-neon-cyan to-blue-500 mx-auto rounded-full mt-8"></div>
            </div>

            <div class="glass-panel p-6 md:p-12 rounded-3xl border border-gray-800 text-gray-300 leading-relaxed space-y-10" data-aos="fade-up">

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <i class="fas fa-user-shield text-neon-cyan"></i> <?php echo __('Information We Collect'); ?>
                    </h3>
                    <p class="mb-4"><?php echo __('We collect information to provide better services to all our users. The types of information we collect include:'); ?></p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-400 marker:text-neon-cyan">
                        <li><strong><?php echo __('Personal Information:'); ?></strong> <?php echo __('Name, email address, phone number, and payment details when you register.'); ?></li>
                        <li><strong><?php echo __('Usage Data:'); ?></strong> <?php echo __('Information on how you use our website, including pages visited and time spent.'); ?></li>
                        <li><strong><?php echo __('Device Information:'); ?></strong> <?php echo __('IP address, browser type, and operating system.'); ?></li>
                    </ul>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <i class="fas fa-server text-neon-cyan"></i> <?php echo __('How We Use Information'); ?>
                    </h3>
                    <p><?php echo __('We use the information we collect to operate and maintain our services, notify you about changes to our service, allow you to participate in interactive features, provide customer support, and detect, prevent and address technical issues.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <i class="fas fa-lock text-neon-cyan"></i> <?php echo __('Data Security'); ?>
                    </h3>
                    <p><?php echo __('The security of your data is important to us, but remember that no method of transmission over the Internet, or method of electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your Personal Data, we cannot guarantee its absolute security.'); ?></p>
                </section>

                <section>
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                        <i class="fas fa-cookie-bite text-neon-cyan"></i> <?php echo __('Cookies'); ?>
                    </h3>
                    <p><?php echo __('We use cookies and similar tracking technologies to track the activity on our Service and hold certain information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.'); ?></p>
                </section>

                <div class="border-t border-gray-800 pt-8 mt-8">
                    <p class="text-sm text-gray-500 text-center">
                        <?php echo __('Last updated:'); ?> <?php echo date("F j, Y"); ?>
                    </p>
                </div>

            </div>
        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>