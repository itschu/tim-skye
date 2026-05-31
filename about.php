<?php

/**
 * About page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Initialize translations
init_translation();

$is_logged_in = isset($_SESSION['user_id']);
$page_title = __('About Us');

// SEO
$page_description = get_setting('about_page_description', get_setting('site_description', __('We are building the most secure and accessible investment ecosystem for the modern world.')));
$page_keywords = get_setting('about_page_keywords', get_setting('site_keywords', 'about,company,investment,platform'));

$company_story = [
    'title' => __('Our Story'),
    'mission' => __('To democratize investment opportunities for everyday people by providing secure, transparent, and easy-to-use tools.'),
    'vision' => __('A world where everyone can build wealth through simple, reliable investing.'),
    'description' => __('Founded in 2020, we set out to create a platform that makes professional investment services accessible to everyone. Over the years we have focused on security, automation, and excellent customer service to help our users achieve their financial goals.')
];

$values = [
    ['icon' => 'fa-shield-halved', 'color' => 'text-neon-purple', 'title' => __('Security First'), 'description' => __('Bank-level encryption and best-practice security safeguards.')],
    ['icon' => 'fa-lightbulb', 'color' => 'text-neon-cyan', 'title' => __('Innovation'), 'description' => __('Cutting-edge technology powering automated investments.')],
    ['icon' => 'fa-handshake', 'color' => 'text-neon-pink', 'title' => __('Transparency'), 'description' => __('Clear fees, clear returns, and honest communication.')]
];

require_once ROOT . '/includes/public-header.php';
?>

<main>
    <section class="relative pt-32 pb-20 overflow-hidden">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-neon-purple/20 blur-[120px] rounded-full -z-10 animate-blob"></div>
        <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-neon-cyan/10 blur-[100px] rounded-full -z-10 animate-blob animation-delay-2000"></div>

        <div class="max-w-7xl mx-auto px-6 lg:px-8 text-center relative z-10">
            <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-6" data-aos="fade-up">
                <?php echo __('Empowering Your'); ?> <span class="text-glow-gradient"><?php echo __('Financial Future'); ?></span>
            </h1>
            <p class="text-gray-400 max-w-2xl mx-auto leading-relaxed" data-aos="fade-up" data-aos-delay="100">
                <?php echo __('We are building the most secure and accessible investment ecosystem for the modern world.'); ?>
            </p>
        </div>
    </section>

    <section class="py-20 bg-dark-900/50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">

                <div data-aos="fade-right">
                    <h2 class="text-3xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-10 h-10 rounded-lg bg-neon-purple/20 flex items-center justify-center text-neon-purple text-xl"><i class="fas fa-rocket"></i></span>
                        <?php echo e($company_story['title']); ?>
                    </h2>
                    <div class="space-y-6 text-gray-400 leading-relaxed">
                        <p><?php echo e($company_story['description']); ?></p>

                        <div class="glass-panel p-6 rounded-2xl border-l-4 border-neon-cyan mt-6">
                            <h4 class="text-white font-bold mb-2"><?php echo __('Our Mission'); ?></h4>
                            <p class="text-sm italic"><?php echo e($company_story['mission']); ?></p>
                        </div>

                        <div class="glass-panel p-6 rounded-2xl border-l-4 border-neon-purple">
                            <h4 class="text-white font-bold mb-2"><?php echo __('Our Vision'); ?></h4>
                            <p class="text-sm italic"><?php echo e($company_story['vision']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="relative" data-aos="fade-left">
                    <div class="absolute inset-0 bg-gradient-to-r from-neon-purple to-neon-cyan rounded-3xl blur-2xl opacity-20"></div>
                    <div class="relative bg-dark-800 border border-gray-700 rounded-3xl p-8 overflow-hidden">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="text-center p-6 bg-dark-900/50 rounded-2xl">
                                <div class="text-3xl font-bold text-neon-cyan mb-1">2020</div>
                                <div class="text-xs text-gray-500 uppercase"><?php echo __('Founded'); ?></div>
                            </div>
                            <div class="text-center p-6 bg-dark-900/50 rounded-2xl">
                                <div class="text-3xl font-bold text-neon-purple">5M+</div>
                                <div class="text-xs text-gray-500 uppercase"><?php echo __('Transactions'); ?></div>
                            </div>
                            <div class="text-center p-6 bg-dark-900/50 rounded-2xl">
                                <div class="text-3xl font-bold text-white">24/7</div>
                                <div class="text-xs text-gray-500 uppercase"><?php echo __('Support'); ?></div>
                            </div>
                            <div class="text-center p-6 bg-dark-900/50 rounded-2xl">
                                <div class="text-3xl font-bold text-green-400">100%</div>
                                <div class="text-xs text-gray-500 uppercase"><?php echo __('Transparency'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="py-24 relative">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center text-white mb-16"><?php echo __('Our Core Values'); ?></h2>

            <div class="grid md:grid-cols-3 gap-8">
                <?php $delay = 0;
                foreach ($values as $val): ?>
                    <div class="glass-panel p-8 rounded-3xl border border-gray-800 hover:border-neon-purple/50 transition-all duration-300 hover:-translate-y-2" data-aos="zoom-in-up" data-aos-delay="<?php echo $delay; ?>">
                        <div class="w-14 h-14 rounded-xl bg-gray-800 flex items-center justify-center text-2xl <?php echo $val['color']; ?> mb-6 shadow-lg shadow-black/20">
                            <i class="fas <?php echo $val['icon']; ?>"></i>
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3"><?php echo e($val['title']); ?></h4>
                        <p class="text-gray-400 leading-relaxed"><?php echo e($val['description']); ?></p>
                    </div>
                <?php $delay += 100;
                endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-20 text-center relative overflow-hidden bg-gradient-to-b from-dark-900 to-black">
        <div class="max-w-4xl mx-auto px-6 relative z-10" data-aos="zoom-in">
            <h2 class="text-3xl md:text-5xl font-bold text-white mb-6"><?php echo __('Ready to get started?'); ?></h2>
            <p class="text-gray-400 mb-8"><?php echo __('Join thousands of investors using our platform to grow their wealth.'); ?></p>

            <?php if (!$is_logged_in): ?>
                <a href="/register" class="inline-block px-10 py-4 bg-neon-purple text-white font-bold rounded-full hover:bg-neon-cyan transition-colors shadow-lg shadow-neon-purple/30">
                    <?php echo __('Join Us Today'); ?>
                </a>
            <?php else: ?>
                <a href="/user/invest" class="inline-block px-10 py-4 bg-neon-cyan text-dark-900 font-bold rounded-full hover:bg-white transition-colors shadow-lg">
                    <?php echo __('View Investment Plans'); ?>
                </a>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>