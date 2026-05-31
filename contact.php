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
    <section class="relative pt-20 pb-20 text-center overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-neon-purple/10 blur-[100px] rounded-full -z-10"></div>
        <div class="max-w-7xl mx-auto px-6">
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4" data-aos="fade-down"><?php echo __('Get in Touch'); ?></h1>
            <p class="text-gray-400" data-aos="fade-up" data-aos-delay="100"><?php echo __('We are here to help you 24/7'); ?></p>
        </div>
    </section>

    <section class="pb-24 pt-20">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12">

                <div data-aos="fade-right">
                    <div class="glass-panel p-8 rounded-3xl border border-gray-800 h-full relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-neon-cyan/10 rounded-full blur-2xl -mr-10 -mt-10"></div>

                        <h3 class="text-2xl font-bold text-white mb-8"><?php echo __('Contact Information'); ?></h3>

                        <div class="space-y-8">
                            <div class="flex items-start gap-5">
                                <div class="w-12 h-12 rounded-xl bg-dark-800 border border-gray-700 flex items-center justify-center text-neon-purple text-xl shrink-0">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <h5 class="text-white font-bold mb-1"><?php echo __('Our Office'); ?></h5>
                                    <p class="text-gray-400 text-sm leading-relaxed"><?php echo nl2br(e(get_setting('contact_address', "123 Business Avenue, Suite 100\nFinancial District, NY 10001"))); ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-5">
                                <div class="w-12 h-12 rounded-xl bg-dark-800 border border-gray-700 flex items-center justify-center text-neon-cyan text-xl shrink-0">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <h5 class="text-white font-bold mb-1"><?php echo __('Email Us'); ?></h5>
                                    <p class="text-gray-400 text-sm mb-1"><a href="mailto:<?php echo e(get_setting('contact_email', 'support@example.com')); ?>" class="hover:text-white transition-colors"><?php echo e(get_setting('contact_email', 'support@example.com')); ?></a></p>
                                    <p class="text-gray-500 text-xs"><?php echo __('Expect a reply within 24 hours'); ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-5">
                                <div class="w-12 h-12 rounded-xl bg-dark-800 border border-gray-700 flex items-center justify-center text-neon-pink text-xl shrink-0">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <h5 class="text-white font-bold mb-1"><?php echo __('Call Us'); ?></h5>
                                    <p class="text-gray-400 text-sm"><a href="tel:<?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?>" class="hover:text-white transition-colors"><?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?></a></p>
                                    <p class="text-gray-500 text-xs"><?php echo __('Mon-Fri from 9am to 6pm'); ?></p>
                                </div>
                            </div>
                        </div>

                        <?php
                        $contact_social_setting = get_setting('social_links', '');
                        $contact_social = [];
                        if ($contact_social_setting) {
                            $decoded = json_decode($contact_social_setting, true);
                            if (is_array($decoded)) $contact_social = $decoded;
                        }

                        $platforms = [
                            'facebook' => ['icon' => 'fab fa-facebook-f', 'hover' => 'hover:border-neon-purple hover:bg-neon-purple/20'],
                            'twitter' => ['icon' => 'fab fa-twitter', 'hover' => 'hover:border-neon-cyan hover:bg-neon-cyan/20'],
                            'linkedin' => ['icon' => 'fab fa-linkedin-in', 'hover' => 'hover:border-blue-500 hover:bg-blue-500/20'],
                            'instagram' => ['icon' => 'fab fa-instagram', 'hover' => 'hover:border-pink-500 hover:bg-pink-500/20'],
                            'youtube' => ['icon' => 'fab fa-youtube', 'hover' => 'hover:border-red-500 hover:bg-red-500/20'],
                            'telegram' => ['icon' => 'fab fa-telegram', 'hover' => 'hover:border-sky-500 hover:bg-sky-500/20']
                        ];

                        $contact_social_non_empty = array_filter($contact_social, fn($v) => !empty($v));
                        ?>

                        <?php if (!empty($contact_social_non_empty)): ?>
                            <div class="mt-12 pt-8 border-t border-gray-800">
                                <h5 class="text-white font-bold mb-4 text-sm uppercase tracking-wide"><?php echo __('Follow Us'); ?></h5>
                                <div class="flex gap-4">
                                    <?php foreach ($platforms as $key => $meta): ?>
                                        <?php if (!empty($contact_social[$key])): ?>
                                            <a href="<?php echo e($contact_social[$key]); ?>" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-full bg-dark-900 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white transition-all <?php echo $meta['hover']; ?>" aria-label="<?php echo ucfirst($key); ?>"><i class="<?php echo $meta['icon']; ?>"></i></a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div data-aos="fade-left">
                    <div class="bg-dark-800 rounded-3xl p-8 lg:p-10 shadow-2xl shadow-black/50 border border-gray-800">
                        <h3 class="text-2xl font-bold text-white mb-6"><?php echo __('Send a Message'); ?></h3>

                        <form action="/actions/contact-submit.php" method="POST" class="space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-xs font-bold text-gray-500 uppercase mb-2"><?php echo __('Name'); ?></label>
                                    <input id="name" name="name" type="text" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-colors" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-xs font-bold text-gray-500 uppercase mb-2"><?php echo __('Email'); ?></label>
                                    <input id="email" name="email" type="email" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-colors" required>
                                </div>
                            </div>

                            <div>
                                <label for="subject" class="block text-xs font-bold text-gray-500 uppercase mb-2"><?php echo __('Subject'); ?></label>
                                <input id="subject" name="subject" type="text" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-colors" required>
                            </div>

                            <div>
                                <label for="message" class="block text-xs font-bold text-gray-500 uppercase mb-2"><?php echo __('Message'); ?></label>
                                <textarea id="message" name="message" rows="5" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-colors" required></textarea>
                            </div>

                            <button type="submit" class="w-full py-4 rounded-xl font-bold text-white bg-gradient-to-r from-neon-purple to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-lg shadow-purple-900/50 transition-all transform hover:-translate-y-1">
                                <i class="fas fa-paper-plane me-2"></i> <?php echo __('Send Message'); ?>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<?php require_once 'includes/public-footer.php'; ?>