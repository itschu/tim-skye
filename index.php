<?php

/**
 * Landing page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Initialize translations (uses default/user preference)
init_translation();

$is_logged_in = isset($_SESSION['user_id']);
$page_title = __('Home');
// SEO
$page_description = get_setting('home_page_description', get_setting('site_description', __('Professional investment platform with automated profit distribution and secure wallet management')));
$page_keywords = get_setting('home_page_keywords', get_setting('site_keywords', 'investment,crypto,investments,finance'));

// 1. FETCH PLANS: Prioritize Featured first, then by ROI
$plans = [];
try {
    $result = db_query("SELECT * FROM investment_plans ORDER BY is_featured DESC, roi_percentage ASC LIMIT 3", []);
    if (is_array($result)) {
        $plans = $result;
    }
} catch (Exception $e) {
    // Silently handle error - $plans remains empty array
    $plans = [];
}

// 2. REORDER FOR DISPLAY: If we have 3 plans, we want the best one (Index 0) in the middle (Index 1)
// Current: [Best, Normal, Normal] -> Desired: [Normal, Best, Normal]
if (is_array($plans) && count($plans) >= 3) {
    $temp = $plans[0];
    $plans[0] = $plans[1];
    $plans[1] = $temp;
}

require_once ROOT . '/includes/public-header.php';
?>

<main>
    <!-- Hero Section -->
    <section class="relative pt-20 pb-20 lg:pt-28 lg:pb-32 overflow-hidden">
        <div class="perspective-grid"></div>
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-neon-purple/20 blur-[120px] rounded-full pointer-events-none -z-10"></div>
        <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-neon-cyan/10 blur-[120px] rounded-full pointer-events-none -z-10"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div data-aos="fade-right" data-aos-duration="1000">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass-panel border-neon-cyan/30 text-cyan-300 text-xs font-bold uppercase tracking-wider mb-8">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                        </span>
                        <?php echo __('AI-Powered Returns'); ?>
                    </div>

                    <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-[1.1] text-white">
                        <?php echo __('Invest Smart.'); ?> <br />
                        <span class="text-glow-gradient"><?php echo __('Grow Faster.'); ?></span>
                    </h1>

                    <p class="text-gray-400 mb-8 max-w-lg leading-relaxed"><?php echo __('Experience the future of wealth generation with our automated trading algorithms. Secure, transparent, and built for growth.'); ?></p>

                    <div class="flex flex-col sm:flex-row gap-4 mb-10">
                        <?php if ($is_logged_in): ?>
                            <a href="/user/invest" class="px-8 py-4 bg-gradient-to-r from-neon-purple to-neon-cyan text-white rounded-full font-bold shadow-lg hover:shadow-neon-cyan/50 transition-all text-center"><?php echo __('Start Investing'); ?></a>
                        <?php else: ?>
                            <a href="/register" class="px-8 py-4 bg-gradient-to-r from-neon-purple to-neon-cyan text-white rounded-full font-bold shadow-lg hover:shadow-neon-cyan/50 transition-all text-center"><?php echo __('Get Started'); ?></a>
                        <?php endif; ?>
                        <?php if (!empty($plans)): ?>
                            <a href="#plans-section" class="px-8 py-4 glass-panel border border-gray-600 text-white rounded-full font-bold hover:bg-white/5 transition-all text-center"><?php echo __('View Plans'); ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex -space-x-3">
                            <div class="w-10 h-10 rounded-full border-2 border-dark-900 bg-gray-700 flex items-center justify-center text-xs font-bold">JD</div>
                            <div class="w-10 h-10 rounded-full border-2 border-dark-900 bg-gray-600 flex items-center justify-center text-xs font-bold">AS</div>
                            <div class="w-10 h-10 rounded-full border-2 border-dark-900 bg-gray-500 flex items-center justify-center text-xs font-bold">MK</div>
                            <div class="w-10 h-10 rounded-full border-2 border-dark-900 bg-neon-cyan text-dark-900 flex items-center justify-center text-xs font-bold">+2k</div>
                        </div>
                        <div class="text-sm text-gray-400"><span class="text-white font-bold">2,500+</span> <?php echo __('investors trust us'); ?></div>
                    </div>
                </div>

                <div class="relative hidden lg:block" data-aos="fade-left" data-aos-duration="1200">
                    <div class="absolute top-0 right-10 w-80 h-96 bg-gray-800 rounded-3xl opacity-30 rotate-12 transform scale-90 blur-sm animate-float-delayed border border-white/10"></div>

                    <div class="relative z-10 w-full max-w-md mx-auto bg-dark-800/90 backdrop-blur-xl rounded-3xl border border-white/10 p-6 shadow-2xl animate-float">
                        <div class="flex justify-between items-center mb-8">
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wider mb-1"><?php echo __('Total Balance'); ?></div>
                                <div class="text-3xl font-bold text-white font-mono">$24,593.00</div>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-neon-cyan/20 flex items-center justify-center text-neon-cyan">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>

                        <div class="h-32 w-full bg-gradient-to-t from-neon-purple/20 to-transparent rounded-xl border border-neon-purple/30 relative overflow-hidden mb-6">
                            <div class="absolute bottom-0 left-0 right-0 h-1 bg-neon-purple shadow-[0_0_15px_#8b5cf6]"></div>
                            <svg viewBox="0 0 100 20" class="absolute bottom-0 w-full h-full text-neon-purple fill-current opacity-20">
                                <path d="M0,10 Q25,20 50,10 T100,10 V20 H0 Z"></path>
                            </svg>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-green-500/20 text-green-400 flex items-center justify-center"><i class="fas fa-arrow-up text-xs"></i></div>
                                    <div class="text-sm text-white font-medium"><?php echo __('ROI Payout'); ?></div>
                                </div>
                                <div class="text-green-400 font-bold text-sm">+$145.20</div>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center"><i class="fas fa-arrow-down text-xs"></i></div>
                                    <div class="text-sm text-white font-medium"><?php echo __('Deposit'); ?></div>
                                </div>
                                <div class="text-white font-bold text-sm">+$500.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute -bottom-10 -left-10 w-24 h-24 bg-dark-900 rounded-2xl border border-gray-700 flex items-center justify-center text-4xl text-neon-cyan shadow-xl animate-bounce z-20">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20 bg-dark-800/50 border-y border-gray-800">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-x divide-gray-800/50" x-data="{ shown: true }" x-intersect.once="shown = true">
                <div class="p-4">
                    <div class="text-3xl md:text-5xl font-bold text-white mb-2 font-mono"><span x-text="shown ? '2.5' : '0'" class="transition-all duration-1000">0</span>k+</div>
                    <p class="text-gray-400 text-xs uppercase tracking-widest"><?php echo __('Active Users'); ?></p>
                </div>

                <div class="p-4">
                    <div class="text-3xl md:text-5xl font-bold text-neon-cyan mb-2 font-mono">$<span x-text="shown ? '5.2' : '0'">0</span>M</div>
                    <p class="text-gray-400 text-xs uppercase tracking-widest"><?php echo __('Total Deposited'); ?></p>
                </div>

                <div class="p-4">
                    <div class="text-3xl md:text-5xl font-bold text-neon-purple mb-2 font-mono">$<span x-text="shown ? '1.8' : '0'">0</span>M</div>
                    <p class="text-gray-400 text-xs uppercase tracking-widest"><?php echo __('Profit Paid'); ?></p>
                </div>

                <div class="p-4">
                    <div class="text-3xl md:text-5xl font-bold text-white mb-2 font-mono"><span x-text="shown ? '24' : '0'">0</span>/7</div>
                    <p class="text-gray-400 text-xs uppercase tracking-widest"><?php echo __('Support'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="py-24 bg-dark-900 relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-bold mb-6 text-white"><?php echo __('Why Choose Us'); ?></h2>
                <p class="text-gray-400"><?php echo __('Superior technology for superior returns.'); ?></p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="glass-panel p-8 rounded-3xl hover-glow-card border border-gray-800" data-aos="zoom-in-up" data-aos-delay="0">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-gray-800 to-black flex items-center justify-center mb-6 border border-gray-700">
                        <i class="fas fa-shield-halved text-2xl text-neon-purple"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-3 text-white"><?php echo __('Secure Platform'); ?></h4>
                    <p class="text-gray-400 leading-relaxed"><?php echo __('Bank-level security with encrypted transactions'); ?></p>
                </div>
                <div class="glass-panel p-8 rounded-3xl hover-glow-card border border-gray-800" data-aos="zoom-in-up" data-aos-delay="100">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-gray-800 to-black flex items-center justify-center mb-6 border border-gray-700">
                        <i class="fas fa-chart-line text-2xl text-neon-cyan"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-3 text-white"><?php echo __('High Returns'); ?></h4>
                    <p class="text-gray-400 leading-relaxed"><?php echo __('Competitive ROI with transparent profit calculations'); ?></p>
                </div>
                <div class="glass-panel p-8 rounded-3xl hover-glow-card border border-gray-800" data-aos="zoom-in-up" data-aos-delay="200">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-gray-800 to-black flex items-center justify-center mb-6 border border-gray-700">
                        <i class="fas fa-clock text-2xl text-neon-pink"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-3 text-white"><?php echo __('Automated Payouts'); ?></h4>
                    <p class="text-gray-400 leading-relaxed"><?php echo __('Scheduled profit distribution to your wallet'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($plans)): ?>
        <!-- Investment Plans Section -->
        <section id="plans-section" class="py-24 relative overflow-hidden">
            <div class="absolute right-0 top-1/2 w-[500px] h-[500px] bg-neon-cyan/20 blur-[100px] -z-10"></div>
            <div class="max-w-7xl mx-auto px-6">
                <h2 class="text-3xl md:text-5xl font-bold text-center mb-16 text-white"><?php echo __('Investment Plans'); ?></h2>

                <div class="grid md:grid-cols-3 gap-8 items-center">
                    <?php $pdelay = 0;
                    foreach ($plans as $index => $plan):
                        // Determine payout label similar to invest page
                        $payout_label = __('Daily');
                        $interval_type = $plan['payout_interval_type'] ?? 'days';
                        $interval_value = isset($plan['payout_interval_value']) ? intval($plan['payout_interval_value']) : null;

                        if (($plan['payout_interval'] ?? '') === 'hourly') {
                            $payout_label = __('Hourly');
                        } elseif (($plan['payout_interval'] ?? '') === 'end_of_term') {
                            $payout_label = __('End of Term');
                        } elseif (($plan['payout_interval'] ?? '') === 'custom' && $interval_value) {
                            $payout_label = format_payout_interval($interval_type, $interval_value);
                        }
                        // Logic: Check if this is the middle element (Index 1) OR if explicitly featured (if array < 3)
                        $is_featured_display = ($index === 1 && count($plans) >= 3) || ($plan['is_featured'] == 1);

                        $roi = isset($plan['roi_percentage']) ? floatval($plan['roi_percentage']) : 0;
                        $link = $is_logged_in ? '/user/invest' : '/register';

                        // Dynamic Styles for Featured vs Standard
                        $card_scale = $is_featured_display ? 'md:scale-110 z-10 shadow-[0_0_40px_rgba(139,92,246,0.15)]' : 'hover:scale-105';
                        $border_color = $is_featured_display ? 'from-neon-purple via-pink-500 to-neon-cyan' : 'from-gray-700 to-gray-800';
                        $bg_color = $is_featured_display ? 'bg-dark-800' : 'bg-dark-900/50';
                        $btn_style = $is_featured_display ? 'bg-gradient-to-r from-neon-purple to-neon-cyan hover:shadow-neon-cyan/50' : 'bg-gray-800 hover:bg-gray-700 border border-gray-600';
                        $text_color = $is_featured_display ? 'text-neon-cyan' : 'text-white';
                    ?>
                        <div class="w-full relative group transition-all duration-300 <?php echo $card_scale; ?>" data-aos="flip-left" data-aos-delay="<?php echo $pdelay; ?>">
                            <div class="absolute -inset-0.5 bg-gradient-to-r <?php echo $border_color; ?> rounded-3xl blur opacity-30 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                            <div class="relative <?php echo $bg_color; ?> rounded-2xl p-8 border border-gray-700 h-full flex flex-col">
                                <?php if ($is_featured_display): ?>
                                    <div class="absolute top-0 right-0 -mt-3 -mr-3">
                                        <span class="bg-gradient-to-r from-neon-purple to-pink-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg uppercase tracking-wider"><?php echo __('Recommended'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <h3 class="text-2xl font-bold mt-3 text-white"><?php echo e($plan['name']); ?></h3>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-3xl font-bold <?php echo $text_color; ?>"><?php echo e(round($plan['roi_percentage'] ?? 0, 2)); ?>%</div>
                                        <div class="text-xs text-gray-500 uppercase"><?php echo e($payout_label); ?></div>
                                    </div>
                                </div>
                                <div class="space-y-4 mb-8 text-gray-300 flex-grow">
                                    <div class="flex justify-between border-b border-gray-700 pb-3">
                                        <span><?php echo __('Min Deposit'); ?></span>
                                        <span class="font-bold text-white"><?php echo format_money($plan['min_amount']); ?></span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-700 pb-3">
                                        <span><?php echo __('Max Deposit'); ?></span>
                                        <span class="font-bold text-white"><?php echo format_money($plan['max_amount']); ?></span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-700 pb-3">
                                        <span><?php echo __('Duration'); ?></span>
                                        <span class="font-bold text-white"><?php echo e($plan['duration_days'] ?? $plan['duration'] ?? __('N/A')); ?> <?php echo __('day(s)'); ?></span>
                                    </div>
                                </div>
                                <a href="<?php echo $link; ?>" class="block w-full py-4 rounded-xl font-bold text-center text-white <?php echo $btn_style; ?> transition-all shadow-lg"><?php echo __('Invest Now'); ?></a>
                            </div>
                        </div>
                    <?php $pdelay += 100;
                    endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- How It Works Section -->
    <section class="py-32 relative bg-dark-900 overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[500px] bg-neon-purple/5 blur-[120px] rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="text-center mb-20" data-aos="fade-up">
                <h2 class="text-3xl md:text-5xl font-bold text-white mb-6"><?php echo __('How It Works'); ?></h2>
                <p class="text-gray-400 max-w-2xl mx-auto"><?php echo __('Start your journey to financial freedom in four simple steps.'); ?></p>
            </div>

            <div class="relative grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="hidden md:block absolute top-12 left-[10%] right-[10%] h-0.5 bg-gradient-to-r from-neon-purple/0 via-neon-purple/50 to-neon-purple/0 z-0"></div>

                <div class="relative group" data-aos="fade-up" data-aos-delay="0">
                    <div class="w-24 h-24 mx-auto glass-panel rounded-2xl flex items-center justify-center text-3xl text-white mb-8 relative z-10 group-hover:-translate-y-2 transition-transform duration-300 border border-gray-700 shadow-[0_0_30px_rgba(139,92,246,0.15)]">
                        <i class="fas fa-user-plus"></i>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-dark-900 rounded-full border border-gray-700 flex items-center justify-center text-sm font-bold text-neon-purple">1</div>
                    </div>
                    <h3 class="text-xl font-bold text-white text-center mb-3"><?php echo __('Register'); ?></h3>
                    <p class="text-gray-400 text-center text-sm leading-relaxed px-4"><?php echo __('Create your secure account in less than 2 minutes.'); ?></p>
                </div>

                <div class="relative group" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-24 h-24 mx-auto glass-panel rounded-2xl flex items-center justify-center text-3xl text-white mb-8 relative z-10 group-hover:-translate-y-2 transition-transform duration-300 border border-gray-700 shadow-[0_0_30px_rgba(6,182,212,0.15)]">
                        <i class="fas fa-wallet"></i>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-dark-900 rounded-full border border-gray-700 flex items-center justify-center text-sm font-bold text-neon-cyan">2</div>
                    </div>
                    <h3 class="text-xl font-bold text-white text-center mb-3"><?php echo __('Deposit'); ?></h3>
                    <p class="text-gray-400 text-center text-sm leading-relaxed px-4"><?php echo __('Fund your wallet using Crypto and Mobile Money transfer.'); ?></p>
                </div>

                <div class="relative group" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-24 h-24 mx-auto glass-panel rounded-2xl flex items-center justify-center text-3xl text-white mb-8 relative z-10 group-hover:-translate-y-2 transition-transform duration-300 border border-gray-700 shadow-[0_0_30px_rgba(236,72,153,0.15)]">
                        <i class="fas fa-layer-group"></i>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-dark-900 rounded-full border border-gray-700 flex items-center justify-center text-sm font-bold text-neon-pink">3</div>
                    </div>
                    <h3 class="text-xl font-bold text-white text-center mb-3"><?php echo __('Invest'); ?></h3>
                    <p class="text-gray-400 text-center text-sm leading-relaxed px-4"><?php echo __('Choose a plan that fits your goals and activate it.'); ?></p>
                </div>

                <div class="relative group" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-24 h-24 mx-auto bg-gradient-to-br from-neon-purple to-neon-cyan rounded-2xl flex items-center justify-center text-3xl text-white mb-8 relative z-10 group-hover:-translate-y-2 transition-transform duration-300 shadow-[0_0_40px_rgba(139,92,246,0.4)]">
                        <i class="fas fa-coins"></i>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-white text-dark-900 rounded-full flex items-center justify-center text-sm font-bold">4</div>
                    </div>
                    <h3 class="text-xl font-bold text-white text-center mb-3"><?php echo __('Profit'); ?></h3>
                    <p class="text-gray-400 text-center text-sm leading-relaxed px-4"><?php echo __('Watch your portfolio grow and withdraw earnings.'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-32 bg-dark-800/30 relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-bold text-white mb-4"><?php echo __('Trusted by Investors'); ?></h2>
                <div class="flex items-center justify-center gap-2 text-yellow-400 text-sm">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    <span class="text-gray-400 ml-2">(4.9/5 <?php echo __('from'); ?> 2,500+ <?php echo __('reviews'); ?>)</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="glass-panel p-8 rounded-3xl border border-gray-700 hover:border-neon-purple/50 transition-colors duration-300 group" data-aos="fade-up">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">AB</div>
                        <div>
                            <h4 class="font-bold text-white">Aisha Bello</h4>
                            <p class="text-xs text-neon-cyan"><?php echo __('Private Investor'); ?></p>
                        </div>
                        <i class="fas fa-quote-right ml-auto text-gray-700 text-2xl group-hover:text-neon-purple transition-colors"></i>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-4">"<?php echo __('I was skeptical at first, but the automated payouts convinced me. The transparency in calculation is unmatched.'); ?>"</p>
                </div>

                <div class="bg-gradient-to-b from-gray-800 to-dark-900 p-8 rounded-3xl border border-gray-700 transform md:-translate-y-4 shadow-xl" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-pink-500 to-orange-500 flex items-center justify-center text-white font-bold text-lg">MR</div>
                        <div>
                            <h4 class="font-bold text-white">Marco Rinaldi</h4>
                            <p class="text-xs text-neon-pink"><?php echo __('Entrepreneur'); ?></p>
                        </div>
                    </div>
                    <p class="text-white leading-relaxed mb-4">"<?php echo __('The low-fee withdrawal policy is a game changer. Customer support helped me set up my portfolio in minutes.'); ?>"</p>
                </div>

                <div class="glass-panel p-8 rounded-3xl border border-gray-700 hover:border-neon-cyan/50 transition-colors duration-300 group" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white font-bold text-lg">LC</div>
                        <div>
                            <h4 class="font-bold text-white">Lina Chen</h4>
                            <p class="text-xs text-neon-cyan"><?php echo __('Freelancer'); ?></p>
                        </div>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-4">"<?php echo __('The dashboard UI is sleek and responsive. I can track my daily ROI easily from my phone.'); ?>"</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-24 relative overflow-hidden">
        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-64 h-64 bg-neon-cyan/10 blur-[80px] rounded-full"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row gap-16">
                <div class="md:w-1/3" data-aos="fade-right">
                    <h2 class="text-3xl md:text-5xl font-bold text-white mb-6"><?php echo __('Common Questions'); ?></h2>
                    <p class="text-gray-400 mb-8 leading-relaxed"><?php echo __('Everything you need to know about investing with us. Can\'t find the answer?'); ?></p>

                    <div class="glass-panel p-6 rounded-2xl border border-gray-700 bg-gradient-to-br from-gray-800/50 to-transparent">
                        <div class="w-12 h-12 bg-neon-purple/20 rounded-lg flex items-center justify-center text-neon-purple mb-4">
                            <i class="fas fa-headset text-xl"></i>
                        </div>
                        <h4 class="text-white font-bold mb-2"><?php echo __('Need Support?'); ?></h4>
                        <p class="text-sm text-gray-400 mb-4"><?php echo __('Our team is available 24/7 to help you.'); ?></p>
                        <a href="/contact" class="text-neon-cyan font-bold text-sm hover:underline"><?php echo __('Contact Support'); ?> &rarr;</a>
                    </div>
                </div>

                <div class="md:w-2/3 space-y-4" data-aos="fade-left">
                    <div x-data="{ open: true }" class="glass-panel rounded-xl border border-gray-700 overflow-hidden transition-all duration-300" :class="{'border-neon-purple/50 bg-gray-800/50': open}">
                        <button @click="open = !open" class="flex justify-between items-center w-full p-5 text-left">
                            <span class="text-lg font-medium text-white"><?php echo __('How do I start investing?'); ?></span>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center transition-colors" :class="{'bg-neon-purple text-white': open, 'bg-gray-800 text-gray-400': !open}">
                                <i class="fas" :class="open ? 'fa-minus' : 'fa-plus'"></i>
                            </div>
                        </button>
                        <div x-show="open" x-collapse class="px-5 pb-5 text-gray-400 border-t border-gray-700/50 pt-4 leading-relaxed">
                            <?php echo __('Sign up for a free account, complete your profile, deposit funds via crypto and mobile money transfer, and select an investment plan that suits your financial goals.'); ?>
                        </div>
                    </div>

                    <div x-data="{ open: false }" class="glass-panel rounded-xl border border-gray-700 overflow-hidden transition-all duration-300" :class="{'border-neon-purple/50 bg-gray-800/50': open}">
                        <button @click="open = !open" class="flex justify-between items-center w-full p-5 text-left">
                            <span class="text-lg font-medium text-white"><?php echo __('Is there a minimum withdrawal?'); ?></span>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center transition-colors" :class="{'bg-neon-purple text-white': open, 'bg-gray-800 text-gray-400': !open}">
                                <i class="fas" :class="open ? 'fa-minus' : 'fa-plus'"></i>
                            </div>
                        </button>
                        <div x-show="open" x-collapse class="px-5 pb-5 text-gray-400 border-t border-gray-700/50 pt-4 leading-relaxed">
                            <?php echo __('Yes, the minimum withdrawal amount depends on the payment method. For most crypto methods, it is as low as $10.'); ?>
                        </div>
                    </div>

                    <div x-data="{ open: false }" class="glass-panel rounded-xl border border-gray-700 overflow-hidden transition-all duration-300" :class="{'border-neon-purple/50 bg-gray-800/50': open}">
                        <button @click="open = !open" class="flex justify-between items-center w-full p-5 text-left">
                            <span class="text-lg font-medium text-white"><?php echo __('What payment methods are supported?'); ?></span>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center transition-colors" :class="{'bg-neon-purple text-white': open, 'bg-gray-800 text-gray-400': !open}">
                                <i class="fas" :class="open ? 'fa-minus' : 'fa-plus'"></i>
                            </div>
                        </button>
                        <div x-show="open" x-collapse class="px-5 pb-5 text-gray-400 border-t border-gray-700/50 pt-4 leading-relaxed">
                            <?php echo __('We support crypto and Mobile Money payments depending on your region.'); ?>
                        </div>
                    </div>

                    <div x-data="{ open: false }" class="glass-panel rounded-xl border border-gray-700 overflow-hidden transition-all duration-300" :class="{'border-neon-purple/50 bg-gray-800/50': open}">
                        <button @click="open = !open" class="flex justify-between items-center w-full p-5 text-left">
                            <span class="text-lg font-medium text-white"><?php echo __('Is my money safe?'); ?></span>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center transition-colors" :class="{'bg-neon-purple text-white': open, 'bg-gray-800 text-gray-400': !open}">
                                <i class="fas" :class="open ? 'fa-minus' : 'fa-plus'"></i>
                            </div>
                        </button>
                        <div x-show="open" x-collapse class="px-5 pb-5 text-gray-400 border-t border-gray-700/50 pt-4 leading-relaxed">
                            <?php echo __('We use industry-standard encryption and secure transaction practices. Always enable 2FA and protect your account credentials.'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 px-6">
        <div class="max-w-6xl mx-auto relative rounded-[3rem] overflow-hidden border border-white/10 group shadow-2xl shadow-neon-purple/20" data-aos="zoom-in">
            <div class="absolute inset-0 bg-dark-900 z-0"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-neon-purple/30 via-transparent to-neon-cyan/20 opacity-80 z-0"></div>

            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-neon-purple/20 rounded-full blur-[120px] pointer-events-none animate-pulse-slow"></div>
            <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-neon-cyan/20 rounded-full blur-[100px] pointer-events-none"></div>

            <div class="relative z-10 py-24 px-6 md:px-20 text-center flex flex-col items-center">
                <div class="inline-block mb-6 px-4 py-1 rounded-full border border-white/20 bg-white/5 backdrop-blur-md text-sm font-medium text-white">🚀 <?php echo __('Join 50,000+ Active Investors'); ?></div>

                <h2 class="text-2xl md:text-6xl font-black text-white mb-6 tracking-tight leading-tight">
                    <?php echo __('Start Growing Your'); ?> <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-neon-cyan to-neon-purple"><?php echo __('Wealth Today'); ?></span>
                </h2>

                <p class="md:text-lg text-gray-300 mb-10 max-w-2xl mx-auto leading-relaxed"><?php echo __('Don\'t let your money sit idle. Join the fastest-growing investment platform and experience automated returns, secure wallets, and 24/7 support.'); ?></p>

                <div class="flex flex-col sm:flex-row gap-5 w-full justify-center">
                    <?php if ($is_logged_in): ?>
                        <a href="/user/invest" class="group relative px-8 py-4 bg-white text-dark-900 rounded-full font-bold text-lg overflow-hidden transition-all hover:scale-105 hover:shadow-[0_0_40px_rgba(255,255,255,0.3)]">
                            <span class="relative z-10"><?php echo __('Start Investing Now'); ?></span>
                        </a>
                    <?php else: ?>
                        <a href="/register" class="group relative px-5 md:px-8 py-3 md:py-4 bg-white text-dark-900 rounded-full font-bold md:text-lg overflow-hidden transition-all hover:scale-105 hover:shadow-[0_0_40px_rgba(255,255,255,0.3)]">
                            <span class="relative z-10"><?php echo __('Create Free Account'); ?></span>
                        </a>
                    <?php endif; ?>
                    <a href="/contact" class="px-5 md:px-8 py-3 md:py-4 bg-white/5 border border-white/20 text-white rounded-full font-bold md:text-lg hover:bg-white/10 transition-colors backdrop-blur-sm"><?php echo __('Talk to an Expert'); ?></a>
                </div>

                <div class="mt-12 flex flex-wrap items-center justify-center gap-6 text-sm text-gray-400 font-medium">
                    <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-400"></i> <?php echo __('No Hidden Fees'); ?></span>
                    <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-400"></i> <?php echo __('Cancel Anytime'); ?></span>
                    <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-400"></i> <?php echo __('Secure & Encrypted'); ?></span>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>