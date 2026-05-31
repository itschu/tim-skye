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
    <section id="hero">
        <div class="hero-bg-img"></div>
        <div class="hero-overlay"></div>
        <div class="hero-grid-bg"></div>
        <div class="hero-glow"></div>

        <div class="ticker-bar" style="top: 64px">
            <div class="ticker-inner">
                <span class="ticker-pair">EUR/USD <span class="up">▲ 1.0842 +0.12%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">GBP/USD <span class="up">▲ 1.2673 +0.08%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">USD/JPY <span class="down">▼ 149.72 −0.05%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">AUD/USD <span class="up">▲ 0.6524 +0.19%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">USD/CAD <span class="down">▼ 1.3618 −0.11%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">NZD/USD <span class="up">▲ 0.5978 +0.07%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">USD/CHF <span class="up">▲ 0.9012 +0.04%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">XAU/USD <span class="up">▲ 2,341.80 +0.31%</span></span>
                <span class="ticker-sep">|</span>
                <!-- duplicate for loop -->
                <span class="ticker-pair">EUR/USD <span class="up">▲ 1.0842 +0.12%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">GBP/USD <span class="up">▲ 1.2673 +0.08%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">USD/JPY <span class="down">▼ 149.72 −0.05%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">AUD/USD <span class="up">▲ 0.6524 +0.19%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">USD/CAD <span class="down">▼ 1.3618 −0.11%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">NZD/USD <span class="up">▲ 0.5978 +0.07%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">USD/CHF <span class="up">▲ 0.9012 +0.04%</span></span>
                <span class="ticker-sep">|</span>
                <span class="ticker-pair">XAU/USD <span class="up">▲ 2,341.80 +0.31%</span></span>
            </div>
        </div>

        <div class="hero-left">
            <div class="eyebrow fu"><?php echo __('Elite Forex Investment Solutions'); ?></div>
            <h1 class="fu d1">
                <?php echo __('Currency markets'); ?><br />
                <em><?php echo __('mastered.'); ?></em><br />
                <?php echo __('Wealth grown.'); ?>
            </h1>
            <p class="hero-sub fu d2"><?php echo __('Tim Skye delivers precision-engineered forex investment solutions for investors who demand consistent, verifiable returns — backed by eight years of live market execution.'); ?></p>
            <div class="btn-row fu d3">
                <?php if ($is_logged_in): ?>
                    <a href="/user/invest" class="btn-gold">
                        <?php echo __('Start Investing'); ?>
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 6.5h9M8 3l3.5 3.5L8 10" /></svg>
                    </a>
                <?php else: ?>
                    <a href="/register" class="btn-gold">
                        <?php echo __('Get Started'); ?>
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 6.5h9M8 3l3.5 3.5L8 10" /></svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($plans)): ?>
                    <a href="#packages" class="btn-outline-sm"><?php echo __('View Plans'); ?> &darr;</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero-right fu d4">
            <div class="hero-stat-stack">
                <div class="hstat">
                    <div class="hstat-val">8+</div>
                    <div class="hstat-lbl"><?php echo __('Years Active Trading'); ?></div>
                </div>
                <div class="hstat">
                    <div class="hstat-val green">92%</div>
                    <div class="hstat-lbl"><?php echo __('Win Rate — 2024'); ?></div>
                </div>
                <div class="hstat">
                    <div class="hstat-val">$12M+</div>
                    <div class="hstat-lbl"><?php echo __('Assets Under Management'); ?></div>
                </div>
                <div class="hero-chart-card">
                    <div class="hcc-label"><?php echo __('Portfolio Growth — 12 Months'); ?></div>
                    <div class="hcc-val">+284%</div>
                    <svg viewBox="0 0 180 50" width="100%" height="50" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="cg" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#3ECFA0" stop-opacity="0.3" />
                                <stop offset="100%" stop-color="#3ECFA0" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="M0 45 L15 40 L30 38 L45 32 L60 28 L75 20 L90 18 L105 14 L120 10 L135 7 L150 4 L165 2 L180 1" stroke="#3ECFA0" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M0 45 L15 40 L30 38 L45 32 L60 28 L75 20 L90 18 L105 14 L120 10 L135 7 L150 4 L165 2 L180 1 L180 50 L0 50Z" fill="url(#cg)" />
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section id="why">
        <div class="why-inner">
            <div class="why-left reveal">
                <div class="section-tag"><?php echo __('Why SeamlessFX'); ?></div>
                <h2 class="section-h"><?php echo __('The edge that sets'); ?><br /><em><?php echo __('us apart'); ?></em></h2>
                <p class="section-lead"><?php echo __('In a market full of noise, Tim Skye\'s approach is built on discipline, data, and decades of earned instinct. Here\'s why thousands of investors choose SeamlessFX.'); ?></p>

                <div class="reasons">
                    <div class="reason reveal">
                        <div class="reason-num">01</div>
                        <div class="reason-body">
                            <h4><?php echo __('Fully Transparent Operations'); ?></h4>
                            <p><?php echo __('Every trade is logged and shared with clients. No smoke, no mirrors — live account access so you can verify every result in real time.'); ?></p>
                        </div>
                    </div>
                    <div class="reason reveal">
                        <div class="reason-num">02</div>
                        <div class="reason-body">
                            <h4><?php echo __('Proven Track Record'); ?></h4>
                            <p><?php echo __('Eight consecutive years of profitable trading, with a 92% win rate across 2024. Numbers don\'t lie — our history speaks for itself.'); ?></p>
                        </div>
                    </div>
                    <div class="reason reveal">
                        <div class="reason-num">03</div>
                        <div class="reason-body">
                            <h4><?php echo __('Strict Risk Management'); ?></h4>
                            <p><?php echo __('Capital preservation is the foundation. We never risk more than 2% per trade and employ dynamic stop-loss strategies on every position.'); ?></p>
                        </div>
                    </div>
                    <div class="reason reveal">
                        <div class="reason-num">04</div>
                        <div class="reason-body">
                            <h4><?php echo __('Tailored to Your Goals'); ?></h4>
                            <p><?php echo __('From conservative income to aggressive compounding, your investment plan is shaped around your timeline, risk tolerance, and targets.'); ?></p>
                        </div>
                    </div>
                    <div class="reason reveal">
                        <div class="reason-num">05</div>
                        <div class="reason-body">
                            <h4><?php echo __('White-Glove Client Service'); ?></h4>
                            <p><?php echo __('You\'re not a ticket number. Elite clients have direct access to Tim, monthly performance calls, and priority account management.'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="why-img-wrap reveal">
                <img class="why-image" src="https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&q=80&auto=format&fit=crop" alt="<?php echo __('Trading charts and market data'); ?>" />
                <div class="why-img-overlay"></div>
                <div class="why-img-caption">"<?php echo __('Precision in every pip. Discipline in every decision.'); ?>"</div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services">
        <div class="svc-header">
            <div>
                <div class="section-tag"><?php echo __('What We Offer'); ?></div>
                <h2 class="section-h"><?php echo __('Core'); ?> <em><?php echo __('Services'); ?></em></h2>
            </div>
            <a href="/contact" class="btn-outline-sm"><?php echo __('Enquire Now'); ?> &rarr;</a>
        </div>

        <div class="svc-grid">
            <div class="svc-card reveal">
                <div class="svc-num">01</div>
                <div class="svc-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2">
                        <polyline points="2,14 6,10 10,12 14,6 18,8" />
                        <path d="M14 6h4v4" />
                    </svg>
                </div>
                <div class="svc-name"><?php echo __('Managed Forex Accounts'); ?></div>
                <p class="svc-desc"><?php echo __('Fully managed trading where Tim executes on your behalf. Your capital, his expertise — with complete transparency and regular reporting.'); ?></p>
            </div>
            <div class="svc-card reveal">
                <div class="svc-num">02</div>
                <div class="svc-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2">
                        <rect x="2" y="4" width="16" height="12" rx="1" />
                        <path d="M7 4v12M13 4v12M2 9h16" />
                    </svg>
                </div>
                <div class="svc-name"><?php echo __('Structured Investment Plans'); ?></div>
                <p class="svc-desc"><?php echo __('Tiered packages designed to match your risk appetite and financial goals — from conservative growth to aggressive compounding.'); ?></p>
            </div>
            <div class="svc-card reveal">
                <div class="svc-num">03</div>
                <div class="svc-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2">
                        <circle cx="10" cy="7" r="3" />
                        <path d="M4 17c0-3.3 2.7-6 6-6s6 2.7 6 6" />
                    </svg>
                </div>
                <div class="svc-name"><?php echo __('FX Mentorship'); ?></div>
                <p class="svc-desc"><?php echo __('One-on-one coaching for aspiring traders. Learn Tim\'s proven system from setup identification to full trade management.'); ?></p>
            </div>
            <div class="svc-card reveal">
                <div class="svc-num">04</div>
                <div class="svc-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2">
                        <circle cx="10" cy="10" r="8" />
                        <path d="M10 6v4l3 3" />
                    </svg>
                </div>
                <div class="svc-name"><?php echo __('Live Signals & Analysis'); ?></div>
                <p class="svc-desc"><?php echo __('Real-time trade signals with full entry, stop, and target levels. Backed by Tim\'s daily market commentary and flow tracking.'); ?></p>
            </div>
            <div class="svc-card reveal">
                <div class="svc-num">05</div>
                <div class="svc-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 4h5v5H4zM11 4h5v5h-5zM4 11h5v5H4zM11 11h5v5h-5z" /></svg>
                </div>
                <div class="svc-name"><?php echo __('Portfolio Diversification'); ?></div>
                <p class="svc-desc"><?php echo __('Strategic allocation across multiple currency pairs and strategies to reduce exposure and optimise risk-adjusted returns.'); ?></p>
            </div>
            <div class="svc-card reveal">
                <div class="svc-num">06</div>
                <div class="svc-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2">
                        <path d="M10 2L3 7v6l7 5 7-5V7L10 2z" />
                        <path d="M10 7v6M7 9l3-2 3 2" />
                    </svg>
                </div>
                <div class="svc-name"><?php echo __('Dedicated Client Support'); ?></div>
                <p class="svc-desc"><?php echo __('Priority access to Tim and his team. Monthly reviews, account updates, and direct communication — always in your corner.'); ?></p>
            </div>
        </div>
    </section>

    <?php if (!empty($plans)): ?>
        <!-- Packages Section -->
        <section id="packages">
            <div class="pkg-intro">
                <div class="section-tag"><?php echo __('Investment Plans'); ?></div>
                <h2 class="section-h"><?php echo __('Choose your'); ?> <em><?php echo __('package'); ?></em></h2>
                <p class="section-lead"><?php echo __('All packages include managed trading, monthly ROI payouts, and dedicated support. Select the tier that aligns with your goals.'); ?></p>
            </div>

            <div class="pkg-grid">
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
                ?>
                    <div class="pkg <?php echo $is_featured_display ? 'featured' : ''; ?> reveal">
                        <?php if ($is_featured_display): ?>
                            <div class="pkg-badge"><?php echo __('Most Popular'); ?></div>
                        <?php endif; ?>
                        <div class="pkg-tier"><?php echo sprintf(__('Tier %02d'), $index + 1); ?></div>
                        <div class="pkg-name"><?php echo e($plan['name']); ?></div>
                        <div class="pkg-min"><?php echo __('Minimum:'); ?> <strong><?php echo format_money($plan['min_amount']); ?></strong></div>
                        <div class="pkg-hr"></div>
                        <div class="pkg-roi"><?php echo e(round($plan['roi_percentage'] ?? 0, 2)); ?>%</div>
                        <div class="pkg-roi-lbl"><?php echo e($payout_label); ?> <?php echo __('ROI Target'); ?></div>
                        <ul class="pkg-feats">
                            <li><?php echo __('Managed FX account'); ?></li>
                            <li><?php echo __('Minimum deposit'); ?> <?php echo format_money($plan['min_amount']); ?></li>
                            <li><?php echo __('Maximum deposit'); ?> <?php echo format_money($plan['max_amount']); ?></li>
                            <li><?php echo __('Duration'); ?> <?php echo e($plan['duration_days'] ?? $plan['duration'] ?? __('N/A')); ?> <?php echo __('day(s)'); ?></li>
                            <li><?php echo __('Transparent reporting'); ?></li>
                        </ul>
                        <a href="<?php echo $link; ?>" class="pkg-btn <?php echo $is_featured_display ? 'solid' : 'outline'; ?>"><?php echo __('Invest Now'); ?></a>
                    </div>
                <?php $pdelay += 100;
                endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Testimonials Section -->
    <section id="testimonials">
        <div class="testi-overlay"></div>
        <div class="testi-inner">
            <div class="testi-header">
                <div class="section-tag"><?php echo __('Client Testimonials'); ?></div>
                <h2 class="section-h"><?php echo __('What our investors'); ?> <em><?php echo __('say'); ?></em></h2>
                <p class="section-lead" style="max-width: 480px; margin: 0.6rem auto 0"><?php echo __('Real results. Real clients. Real growth.'); ?></p>
            </div>
            <div class="testi-grid">
                <div class="tcard reveal">
                    <div class="tcard-stars">
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                    </div>
                    <p class="tcard-text">"<?php echo __('I was skeptical at first, but Tim\'s transparency won me over immediately. Full access to trade history, weekly updates, and my Growth account has returned 19% this month. Absolutely remarkable.'); ?>"</p>
                    <div class="tcard-author">
                        <img class="tcard-avatar" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&q=80&auto=format&fit=crop&crop=face" alt="<?php echo __('James O.'); ?>" />
                        <div>
                            <div class="tcard-name"><?php echo __('James O.'); ?></div>
                            <div class="tcard-role"><?php echo __('Software Engineer, UK'); ?></div>
                            <div class="tcard-amount">+19% <?php echo __('this month'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="tcard reveal">
                    <div class="tcard-stars">
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                    </div>
                    <p class="tcard-text">"<?php echo __('SeamlessFX changed how I think about passive income. I started with the Starter plan six months ago and upgraded to Elite after seeing consistent results. Tim is the real deal — disciplined and communicative.'); ?>"</p>
                    <div class="tcard-author">
                        <img class="tcard-avatar" src="https://images.unsplash.com/photo-1494790108755-2616b612b47c?w=80&h=80&q=80&auto=format&fit=crop&crop=face" alt="<?php echo __('Amara N.'); ?>" />
                        <div>
                            <div class="tcard-name"><?php echo __('Amara N.'); ?></div>
                            <div class="tcard-role"><?php echo __('Business Owner, Nigeria'); ?></div>
                            <div class="tcard-amount">+284% <?php echo __('over 6 months'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="tcard reveal">
                    <div class="tcard-stars">
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                    </div>
                    <p class="tcard-text">"<?php echo __('The Elite package is worth every penny. I have a direct line to Tim, weekly withdrawal options, and a custom strategy built around my retirement timeline. This is the kind of service you\'d expect from a private bank.'); ?>"</p>
                    <div class="tcard-author">
                        <img class="tcard-avatar" src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&q=80&auto=format&fit=crop&crop=face" alt="<?php echo __('Marcus T.'); ?>" />
                        <div>
                            <div class="tcard-name"><?php echo __('Marcus T.'); ?></div>
                            <div class="tcard-role"><?php echo __('Retired Executive, USA'); ?></div>
                            <div class="tcard-amount"><?php echo __('Elite — $50k managed'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="tcard reveal">
                    <div class="tcard-stars">
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                    </div>
                    <p class="tcard-text">"<?php echo __('I have tried several managed forex services and none come close to Tim\'s level of professionalism. Reporting is impeccable, returns are consistent, and he actually picks up the phone when you call.'); ?>"</p>
                    <div class="tcard-author">
                        <div class="tcard-avatar-init">RK</div>
                        <div>
                            <div class="tcard-name"><?php echo __('Rashida K.'); ?></div>
                            <div class="tcard-role"><?php echo __('Investor, South Africa'); ?></div>
                            <div class="tcard-amount">+22% <?php echo __('last month'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="tcard reveal">
                    <div class="tcard-stars">
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                    </div>
                    <p class="tcard-text">"<?php echo __('Started with just $500 on the Starter plan to test the waters. After seeing 11% returns in month one, I moved $5,000 into Growth. Three months in and I\'ve already recouped my initial investment twice over.'); ?>"</p>
                    <div class="tcard-author">
                        <img class="tcard-avatar" src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80&h=80&q=80&auto=format&fit=crop&crop=face" alt="<?php echo __('Daniel F.'); ?>" />
                        <div>
                            <div class="tcard-name"><?php echo __('Daniel F.'); ?></div>
                            <div class="tcard-role"><?php echo __('Freelancer, Canada'); ?></div>
                            <div class="tcard-amount"><?php echo __('Started at $500'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="tcard reveal">
                    <div class="tcard-stars">
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                        <div class="star"></div>
                    </div>
                    <p class="tcard-text">"<?php echo __('What I appreciate most is the education alongside the returns. Tim explains his reasoning behind trades, which has made me a better investor overall. I\'m up 31% this quarter and learning every week.'); ?>"</p>
                    <div class="tcard-author">
                        <div class="tcard-avatar-init">YM</div>
                        <div>
                            <div class="tcard-name"><?php echo __('Yemi M.'); ?></div>
                            <div class="tcard-role"><?php echo __('Doctor, Ghana'); ?></div>
                            <div class="tcard-amount">+31% <?php echo __('this quarter'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Band -->
    <section id="trust">
        <div class="trust-inner">
            <div class="trust-label"><?php echo __('Traded Across'); ?></div>
            <div class="trust-divider"></div>
            <div class="trust-logos">
                <div class="trust-logo-text"><?php echo __('MetaTrader 4'); ?></div>
                <div class="trust-logo-text"><?php echo __('MetaTrader 5'); ?></div>
                <div class="trust-logo-text"><?php echo __('cTrader'); ?></div>
                <div class="trust-logo-text"><?php echo __('IC Markets'); ?></div>
                <div class="trust-logo-text"><?php echo __('Pepperstone'); ?></div>
                <div class="trust-logo-text"><?php echo __('FXCM'); ?></div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="cta">
        <div class="cta-glow"></div>
        <div class="cta-body">
            <div class="section-tag" style="justify-content: center; margin-bottom: 1.3rem"><?php echo __('Get Started Today'); ?></div>
            <h2 class="cta-h"><?php echo __('Your capital should be'); ?><br /><em><?php echo __('working for you.'); ?></em></h2>
            <p class="cta-sub"><?php echo __('Join hundreds of investors already growing their wealth through SeamlessFX. Drop your email and we\'ll reach out within 24 hours to discuss your plan.'); ?></p>
            <form class="cta-form" onsubmit="event.preventDefault(); const btn = this.querySelector('button'); btn.textContent = '<?php echo __('Submitted'); ?> ✓'; btn.style.background = '#3ECFA0'; setTimeout(() => { btn.textContent = '<?php echo __('Join Now'); ?>'; btn.style.background = ''; this.reset(); }, 3200);">
                <input type="email" placeholder="<?php echo __('Your email address'); ?>" required />
                <button type="submit"><?php echo __('Join Now'); ?></button>
            </form>
            <p class="cta-disc"><?php echo __('No spam. Capital at risk — past performance does not guarantee future results.'); ?></p>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq">
        <div class="faq-wrap">
            <div class="testi-header" style="margin-bottom: 4rem;">
                <div class="section-tag" style="justify-content: center;"><?php echo __('Common Questions'); ?></div>
                <h2 class="section-h"><?php echo __('Frequently Asked'); ?> <em><?php echo __('Questions'); ?></em></h2>
                <p class="section-lead" style="max-width: 480px; margin: 0.6rem auto 0"><?php echo __('Everything you need to know about investing with us. Can\'t find the answer?'); ?></p>
            </div>

            <div class="faq-item" x-data="{ open: true }">
                <button @click="open = !open" class="faq-q">
                    <span><?php echo __('How do I start investing?'); ?></span>
                    <div class="faq-icon" :class="{ 'open': open }">+</div>
                </button>
                <div x-show="open" x-collapse class="faq-a">
                    <?php echo __('Sign up for a free account, complete your profile, deposit funds via crypto and mobile money transfer, and select an investment plan that suits your financial goals.'); ?>
                </div>
            </div>

            <div class="faq-item" x-data="{ open: false }">
                <button @click="open = !open" class="faq-q">
                    <span><?php echo __('Is there a minimum withdrawal?'); ?></span>
                    <div class="faq-icon" :class="{ 'open': open }">+</div>
                </button>
                <div x-show="open" x-collapse class="faq-a">
                    <?php echo __('Yes, the minimum withdrawal amount depends on the payment method. For most crypto methods, it is as low as $10.'); ?>
                </div>
            </div>

            <div class="faq-item" x-data="{ open: false }">
                <button @click="open = !open" class="faq-q">
                    <span><?php echo __('What payment methods are supported?'); ?></span>
                    <div class="faq-icon" :class="{ 'open': open }">+</div>
                </button>
                <div x-show="open" x-collapse class="faq-a">
                    <?php echo __('We support crypto and Mobile Money payments depending on your region.'); ?>
                </div>
            </div>

            <div class="faq-item" x-data="{ open: false }">
                <button @click="open = !open" class="faq-q">
                    <span><?php echo __('Is my money safe?'); ?></span>
                    <div class="faq-icon" :class="{ 'open': open }">+</div>
                </button>
                <div x-show="open" x-collapse class="faq-a">
                    <?php echo __('We use industry-standard encryption and secure transaction practices. Always enable 2FA and protect your account credentials.'); ?>
                </div>
            </div>

            <div style="margin-top: 3rem; border: 0.5px solid var(--border); padding: 1.5rem; text-align: center;">
                <h4 style="font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; color: var(--gold-light); margin-bottom: 0.5rem;"><?php echo __('Need Support?'); ?></h4>
                <p style="font-size: 0.85rem; color: var(--muted-light); margin-bottom: 1rem;"><?php echo __('Our team is available 24/7 to help you.'); ?></p>
                <a href="/contact" class="btn-outline-sm"><?php echo __('Contact Support'); ?> &rarr;</a>
            </div>
        </div>
    </section>

    <script>
        // Scroll reveal
        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((en) => {
                    if (en.isIntersecting) en.target.classList.add('shown');
                });
            },
            { threshold: 0.08 },
        );
        document.querySelectorAll('.reveal').forEach((el) => io.observe(el));

        // Stagger children in grids
        document.querySelectorAll('.testi-grid .tcard, .svc-grid .svc-card, .pkg-grid .pkg').forEach((el, i) => {
            el.style.transitionDelay = (i % 3) * 0.12 + 's';
        });
    </script>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
