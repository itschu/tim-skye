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
    ['icon' => 'shield', 'title' => __('Security First'), 'description' => __('Bank-level encryption and best-practice security safeguards.')],
    ['icon' => 'lightbulb', 'title' => __('Innovation'), 'description' => __('Cutting-edge technology powering automated investments.')],
    ['icon' => 'handshake', 'title' => __('Transparency'), 'description' => __('Clear fees, clear returns, and honest communication.')]
];

require_once ROOT . '/includes/public-header.php';
?>

<main>
    <!-- HERO -->
    <section id="hero" style="min-height:60vh;">
        <div class="hero-bg-img"></div>
        <div class="hero-overlay"></div>
        <div class="hero-grid-bg"></div>
        <div class="hero-glow"></div>

        <div class="hero-left" style="z-index:2; position:relative;">
            <div class="eyebrow fu"><?php echo __('Who We Are'); ?></div>
            <h1 class="fu d1">
                <?php echo __('Empowering Your'); ?> <em><?php echo __('Financial Future'); ?></em>
            </h1>
            <p class="hero-sub fu d2"><?php echo __('We are building the most secure and accessible investment ecosystem for the modern world.'); ?></p>
            <div class="btn-row fu d3">
                <?php if ($is_logged_in): ?>
                    <a href="/user/invest" class="btn-gold"><?php echo __('Start Investing'); ?> <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 6.5h9M8 3l3.5 3.5L8 10" /></svg></a>
                <?php else: ?>
                    <a href="/register" class="btn-gold"><?php echo __('Get Started'); ?> <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 6.5h9M8 3l3.5 3.5L8 10" /></svg></a>
                <?php endif; ?>
                <a href="/contact" class="btn-outline-sm"><?php echo __('Contact Us'); ?> →</a>
            </div>
        </div>
    </section>

    <!-- ABOUT STORY -->
    <section id="about">
        <div class="about-img-wrap reveal">
            <img class="about-img" src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=700&q=80&auto=format&fit=crop" alt="<?php echo __('Our team at work'); ?>" />
            <div class="about-img-border"></div>
            <div class="about-corner-tl"></div>
            <div class="about-badge-img"><?php echo __('Since 2020'); ?></div>
        </div>
        <div class="about-text reveal">
            <div class="section-tag"><?php echo e($company_story['title']); ?></div>
            <h2 class="section-h"><?php echo __('A platform built on'); ?> <em><?php echo __('trust'); ?></em></h2>
            <div class="about-hr"></div>
            <p><?php echo e($company_story['description']); ?></p>

            <div class="about-hr" style="margin:1.2rem 0;"></div>
            <p><strong><?php echo __('Mission:'); ?></strong> <?php echo e($company_story['mission']); ?></p>
            <p><strong><?php echo __('Vision:'); ?></strong> <?php echo e($company_story['vision']); ?></p>

            <div class="creds">
                <div>
                    <div class="cred-val">2020</div>
                    <div class="cred-lbl"><?php echo __('Founded'); ?></div>
                </div>
                <div>
                    <div class="cred-val">400+</div>
                    <div class="cred-lbl"><?php echo __('Active Clients'); ?></div>
                </div>
                <div>
                    <div class="cred-val">24/7</div>
                    <div class="cred-lbl"><?php echo __('Support'); ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- VALUES -->
    <section id="services">
        <div class="svc-header">
            <div>
                <div class="section-tag"><?php echo __('Our Principles'); ?></div>
                <h2 class="section-h"><?php echo __('Core '); ?><em><?php echo __('Values'); ?></em></h2>
            </div>
        </div>

        <div class="svc-grid">
            <?php $delay = 0; foreach ($values as $i => $val): ?>
                <div class="svc-card reveal" style="transition-delay:<?php echo $delay * 0.12; ?>s">
                    <div class="svc-num"><?php echo sprintf('%02d', $i + 1); ?></div>
                    <div class="svc-icon">
                        <?php if ($val['icon'] === 'shield'): ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M10 2L4 5v5c0 5 2.5 7.5 6 8 3.5-.5 6-3 6-8V5l-6-3z"/></svg>
                        <?php elseif ($val['icon'] === 'lightbulb'): ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M10 2a5 5 0 0 1 5 5c0 2-1.5 3.5-2 5h-6c-.5-1.5-2-3-2-5a5 5 0 0 1 5-5zM7 14h6M8 17h4"/></svg>
                        <?php else: ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 10h12M4 14h12M4 6h12"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="svc-name"><?php echo e($val['title']); ?></div>
                    <p class="svc-desc"><?php echo e($val['description']); ?></p>
                </div>
            <?php $delay++; endforeach; ?>
        </div>
    </section>

    <!-- CTA -->
    <section id="cta">
        <div class="cta-glow"></div>
        <div class="cta-body">
            <div class="section-tag" style="justify-content:center; margin-bottom:1.3rem"><?php echo __('Get Started Today'); ?></div>
            <h2 class="cta-h"><?php echo __('Ready to grow your'); ?> <em><?php echo __('wealth?'); ?></em></h2>
            <p class="cta-sub"><?php echo __('Join thousands of investors already growing their wealth through our platform.'); ?></p>
            <div class="btn-row" style="justify-content:center;">
                <?php if ($is_logged_in): ?>
                    <a href="/user/invest" class="btn-gold"><?php echo __('Start Investing'); ?> <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 6.5h9M8 3l3.5 3.5L8 10" /></svg></a>
                <?php else: ?>
                    <a href="/register" class="btn-gold"><?php echo __('Create Free Account'); ?> <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 6.5h9M8 3l3.5 3.5L8 10" /></svg></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
