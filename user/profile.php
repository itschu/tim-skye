<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Account Settings');
$active_nav = 'profile';
$user_id = $_SESSION['user_id'];

// Get user data
$userRows = db_query("SELECT * FROM users WHERE id = ?", [$user_id]);
$user = !empty($userRows) ? $userRows[0] : null;

// Get KYC documents (single-row schema)
$kyc = db_query('SELECT * FROM kyc_documents WHERE user_id = ?', [$user_id])[0] ?? null;

// Check if KYC is enabled
$kyc_required = get_setting('kyc_required', 'no');
$kyc_enabled = ($kyc_required === 'yes');

// Get KYC status
$kyc_status = $user['kyc_status'] ?? 'not_submitted';

// Country list
$countries = get_countries();

$profile_picture_url = $user['profile_picture'] ?? null;
$initial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$member_year = !empty($user['created_at']) ? date('y', strtotime($user['created_at'])) : date('y');

// Extra CSS for premium inputs, drop zones and modal transitions
ob_start();
?>
<style type="text/tailwindcss">
    .premium-input {
        @apply w-full bg-[#09090b] border border-zinc-800 text-zinc-100 text-sm rounded-xl px-4 py-3.5 focus:outline-none focus:border-brand-accent/50 focus:ring-1 focus:ring-brand-accent/50 transition-all placeholder:text-zinc-600;
    }
    .premium-input[readonly],
    .premium-input:disabled {
        @apply text-zinc-500 cursor-not-allowed opacity-70;
    }
    .premium-input-select {
        @apply appearance-none cursor-pointer;
    }
    .upload-zone {
        @apply relative border-2 border-dashed border-zinc-700 rounded-2xl p-6 text-center transition-all bg-zinc-900/50 cursor-pointer hover:border-brand-accent/50 hover:bg-brand-accent/5;
    }
    .upload-zone-selected {
        @apply border-solid border-brand-accent bg-brand-accent/10;
    }
</style>
<?php
$extra_css = ob_get_clean();

// Extra inline profile scripts
ob_start();
?>
<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const modalBox = modal.querySelector('.modal-box');
        modal.classList.remove('opacity-0', 'invisible');
        if (modalBox) modalBox.classList.remove('scale-95');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const modalBox = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0', 'invisible');
        if (modalBox) modalBox.classList.add('scale-95');
        document.body.style.overflow = 'auto';
    }

    // Help center AJAX form submission
    const helpForm = document.querySelector('#modal-help form');
    const helpStatus = document.getElementById('help-status');
    const helpSubmitBtn = helpForm ? helpForm.querySelector('button[type="submit"]') : null;
    const helpOriginalBtnHtml = helpSubmitBtn ? helpSubmitBtn.innerHTML : '';
    const sendingText = <?php echo json_encode(__('Sending...')); ?>;

    if (helpForm) {
        helpForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (helpStatus) {
                helpStatus.classList.add('hidden');
                helpStatus.classList.remove(
                    'bg-emerald-500/10', 'text-emerald-400', 'border', 'border-emerald-500/20',
                    'bg-red-500/10', 'text-red-400', 'border-red-500/20'
                );
            }

            if (helpSubmitBtn) {
                helpSubmitBtn.disabled = true;
                helpSubmitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ' + sendingText;
            }

            try {
                const response = await fetch(helpForm.action, {
                    method: 'POST',
                    body: new FormData(helpForm),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (data && data.success) {
                    if (helpStatus) {
                        helpStatus.textContent = data.message || <?php echo json_encode(__('Message sent successfully.')); ?>;
                        helpStatus.classList.remove('hidden');
                        helpStatus.classList.add('bg-emerald-500/10', 'text-emerald-400', 'border', 'border-emerald-500/20');
                    }
                    helpForm.reset();
                } else {
                    throw new Error(data && data.message ? data.message : <?php echo json_encode(__('Failed to send message.')); ?>);
                }
            } catch (err) {
                if (helpStatus) {
                    helpStatus.textContent = err.message || <?php echo json_encode(__('An error occurred. Please try again.')); ?>;
                    helpStatus.classList.remove('hidden');
                    helpStatus.classList.add('bg-red-500/10', 'text-red-400', 'border', 'border-red-500/20');
                }
            }

            if (helpSubmitBtn) {
                helpSubmitBtn.disabled = false;
                helpSubmitBtn.innerHTML = helpOriginalBtnHtml;
            }
        });
    }
</script>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
?>

<div class="w-full max-w-3xl mx-auto relative z-10"
     x-data="{
         profilePreview: <?php echo htmlspecialchars(json_encode($profile_picture_url ? '/' . ltrim($profile_picture_url, '/') : null), ENT_QUOTES, 'UTF-8'); ?>,
         defaultAvatarUrl: <?php echo htmlspecialchars(json_encode('https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&background=10b981&color=09090b'), ENT_QUOTES, 'UTF-8'); ?>,
         selectedFiles: { id_passport: null, proof_address: null, selfie: null },
         allSelected() {
             return !!(this.selectedFiles.id_passport && this.selectedFiles.proof_address && this.selectedFiles.selfie);
         },
         previewImage(event) {
             const file = event.target.files[0];
             if (file) {
                 const reader = new FileReader();
                 reader.onload = (e) => { this.profilePreview = e.target.result; };
                 reader.readAsDataURL(file);
             }
         }
     }"
     x-init="if (window.location.hash === '#kyc') setTimeout(() => openModal('modal-kyc'), 100)">

    <!-- Mobile header -->
    <div class="md:hidden flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-white tracking-tight"><?php echo e(__('My Profile')); ?></h1>
        <button type="button" class="w-10 h-10 rounded-xl bg-brand-card border border-zinc-800 flex items-center justify-center text-zinc-400">
            <i class="fa-regular fa-bell"></i>
        </button>
    </div>

    <!-- Profile hero card -->
    <div class="bg-brand-card/80 backdrop-blur-md border border-zinc-800/60 rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-center gap-6 relative overflow-hidden shadow-2xl mb-8">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-brand-accent/10 rounded-full blur-3xl"></div>

        <div class="relative group cursor-pointer shrink-0 z-10">
            <?php if ($profile_picture_url): ?>
                <img id="avatarPreview"
                     :src="profilePreview || defaultAvatarUrl"
                     src="<?php echo e('/' . ltrim($profile_picture_url, '/')); ?>"
                     class="w-24 h-24 rounded-full object-cover border-2 border-brand-accent/30 shadow-[0_0_20px_rgba(16,185,129,0.15)] group-hover:border-brand-accent transition-colors z-10 relative"
                     alt="<?php echo e($user['name'] ?? 'User'); ?>">
            <?php else: ?>
                <div class="w-24 h-24 rounded-full bg-brand-dark border-2 border-brand-accent/30 flex items-center justify-center text-4xl font-bold text-brand-accent shadow-[0_0_20px_rgba(16,185,129,0.15)] group-hover:border-brand-accent transition-colors z-10 relative">
                    <?php echo e($initial); ?>
                </div>
            <?php endif; ?>
            <button type="button"
                    class="absolute bottom-0 right-0 w-8 h-8 bg-zinc-800 rounded-full border-2 border-brand-card flex items-center justify-center text-zinc-300 z-20 hover:bg-zinc-700 transition-colors"
                    onclick="document.getElementById('avatarUpload').click()">
                <i class="fa-solid fa-camera text-xs"></i>
            </button>
        </div>

        <div class="text-center md:text-left flex-1 z-10">
            <h2 class="text-2xl font-bold text-white flex items-center justify-center md:justify-start gap-2">
                <?php echo e($user['name'] ?? 'User'); ?>
                <?php if ($kyc_enabled && $kyc_status === 'approved'): ?>
                    <i class="fa-solid fa-circle-check text-brand-accent text-sm" title="<?php echo e(__('Verified Account')); ?>"></i>
                <?php endif; ?>
            </h2>
            <p class="text-zinc-400 mt-1"><?php echo e($user['email'] ?? ''); ?></p>

            <div class="mt-4 flex flex-wrap items-center justify-center md:justify-start gap-3">
                <?php if ($kyc_enabled): ?>
                    <?php if ($kyc_status === 'approved'): ?>
                        <span class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1.5 rounded-full text-emerald-500 text-xs font-semibold">
                            <i class="fa-solid fa-shield-halved"></i> <?php echo e(__('Verified')); ?>
                        </span>
                    <?php elseif ($kyc_status === 'pending'): ?>
                        <span class="inline-flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 px-3 py-1.5 rounded-full text-amber-500 text-xs font-semibold">
                            <i class="fa-solid fa-clock"></i> <?php echo e(__('KYC Pending')); ?>
                        </span>
                    <?php elseif ($kyc_status === 'rejected'): ?>
                        <span class="inline-flex items-center gap-2 bg-rose-500/10 border border-rose-500/20 px-3 py-1.5 rounded-full text-rose-500 text-xs font-semibold">
                            <i class="fa-solid fa-circle-exclamation"></i> <?php echo e(__('Rejected')); ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 bg-zinc-800/50 border border-zinc-700/50 px-3 py-1.5 rounded-full text-zinc-300 text-xs font-medium">
                            <i class="fa-solid fa-user"></i> <?php echo e(__('Not Verified')); ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="inline-flex items-center gap-2 bg-zinc-800/50 border border-zinc-700/50 px-3 py-1.5 rounded-full text-zinc-300 text-xs font-medium">
                    <?php echo e(__('Member since')); ?> '<?php echo e($member_year); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Settings list -->
    <div class="space-y-6">
        <!-- Account Setup -->
        <div>
            <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider px-2 mb-3"><?php echo e(__('Account Setup')); ?></h3>
            <div class="bg-brand-dark/50 border border-zinc-800/60 rounded-3xl p-2 space-y-1">
                <button type="button" onclick="openModal('modal-personal-info')" class="flex items-center gap-4 hover:bg-brand-card p-3 rounded-2xl transition-all group cursor-pointer w-full text-left">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center border border-blue-500/20 group-hover:scale-105 transition-transform shrink-0">
                        <i class="fa-regular fa-id-badge text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-zinc-100 font-semibold text-sm group-hover:text-brand-accent transition-colors"><?php echo e(__('Personal Information')); ?></h4>
                        <p class="text-xs text-zinc-500 mt-0.5"><?php echo e(__('Update your identity details & contact')); ?></p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-zinc-600 group-hover:text-zinc-300 transition-colors mr-2"></i>
                </button>

                <?php if ($kyc_enabled): ?>
                    <button type="button" onclick="openModal('modal-kyc')" class="flex items-center gap-4 hover:bg-brand-card p-3 rounded-2xl transition-all group cursor-pointer w-full text-left">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center border border-emerald-500/20 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-id-card text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-zinc-100 font-semibold text-sm group-hover:text-brand-accent transition-colors"><?php echo e(__('KYC Verification')); ?></h4>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                <?php if ($kyc_status === 'approved'): ?>
                                    <?php echo e(__('Your identity is verified')); ?>
                                <?php elseif ($kyc_status === 'pending'): ?>
                                    <?php echo e(__('Documents under review')); ?>
                                <?php elseif ($kyc_status === 'rejected'): ?>
                                    <?php echo e(__('Verification rejected - resubmit')); ?>
                                <?php else: ?>
                                    <?php echo e(__('Upload documents to unlock full access')); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 mr-2">
                            <?php if ($kyc_status !== 'approved'): ?>
                                <span class="bg-rose-500 rounded-full inline-block" style="width: 8px; height: 8px"></span>
                            <?php endif; ?>
                            <i class="fa-solid fa-chevron-right text-zinc-600 group-hover:text-zinc-300 transition-colors"></i>
                        </div>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security -->
        <div>
            <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider px-2 mb-3"><?php echo e(__('Security')); ?></h3>
            <div class="bg-brand-dark/50 border border-zinc-800/60 rounded-3xl p-2 space-y-1">
                <button type="button" onclick="openModal('modal-password')" class="flex items-center gap-4 hover:bg-brand-card p-3 rounded-2xl transition-all group cursor-pointer w-full text-left">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center border border-amber-500/20 group-hover:scale-105 transition-transform shrink-0">
                        <i class="fa-solid fa-lock text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-zinc-100 font-semibold text-sm group-hover:text-brand-accent transition-colors"><?php echo e(__('Change Password')); ?></h4>
                        <p class="text-xs text-zinc-500 mt-0.5"><?php echo e(__('Ensure your account stays secure')); ?></p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-zinc-600 group-hover:text-zinc-300 transition-colors mr-2"></i>
                </button>
            </div>
        </div>

        <!-- Support & About -->
        <div>
            <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider px-2 mb-3"><?php echo e(__('Support & About')); ?></h3>
            <div class="bg-brand-dark/50 border border-zinc-800/60 rounded-3xl p-2 space-y-1">
                <button type="button" onclick="openModal('modal-help')" class="flex items-center gap-4 hover:bg-brand-card p-3 rounded-2xl transition-all group cursor-pointer w-full text-left">
                    <div class="w-12 h-12 rounded-xl bg-zinc-800 text-zinc-300 flex items-center justify-center border border-zinc-700 group-hover:scale-105 transition-transform shrink-0">
                        <i class="fa-solid fa-headset text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-zinc-100 font-semibold text-sm group-hover:text-brand-accent transition-colors"><?php echo e(__('Help Center')); ?></h4>
                        <p class="text-xs text-zinc-500 mt-0.5"><?php echo e(__('Contact support for assistance')); ?></p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-zinc-600 group-hover:text-zinc-300 transition-colors mr-2"></i>
                </button>
            </div>
        </div>

        <!-- Sign out -->
        <?php if (!isset($_SESSION['admin_original_id'])): ?>
            <a href="/logout" class="w-full mt-6 flex items-center justify-center gap-3 p-4 rounded-2xl bg-rose-500/10 text-rose-500 font-bold border border-rose-500/20 hover:bg-rose-500 hover:text-white transition-all hover:shadow-[0_0_20px_rgba(244,63,94,0.2)]">
                <i class="fa-solid fa-power-off"></i> <?php echo e(__('Secure Sign Out')); ?>
            </a>
        <?php else: ?>
            <form method="POST" action="/admin/actions/exit-login-as" class="w-full mt-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <button type="submit" class="w-full flex items-center justify-center gap-3 p-4 rounded-2xl bg-rose-500/10 text-rose-500 font-bold border border-rose-500/20 hover:bg-rose-500 hover:text-white transition-all hover:shadow-[0_0_20px_rgba(244,63,94,0.2)]">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> <?php echo e(__('Back To Admin')); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Personal Information Modal -->
    <div id="modal-personal-info" class="fixed inset-0 z-[60] flex items-center justify-center px-4 opacity-0 invisible transition-all duration-300">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeModal('modal-personal-info')"></div>
    <div class="relative bg-brand-card border border-zinc-800/60 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-box">
        <div class="h-1 w-full bg-gradient-to-r from-blue-500/10 via-blue-500 to-blue-500/10"></div>

        <div class="p-6 overflow-y-auto max-h-[85vh]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white tracking-tight"><?php echo e(__('Personal Info')); ?></h3>
                <button type="button" onclick="closeModal('modal-personal-info')" class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="/actions/profile-update.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="file" id="avatarUpload" name="profile_picture" class="hidden" accept="image/*" @change="previewImage($event)">

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Full Name')); ?></label>
                    <input type="text" name="name" class="premium-input" value="<?php echo e($user['name'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Email Address')); ?></label>
                    <input type="email" class="premium-input" value="<?php echo e($user['email'] ?? ''); ?>" readonly title="<?php echo e(__('Contact support to change email')); ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Phone Number')); ?></label>
                    <input type="tel" name="phone" class="premium-input" value="<?php echo e($user['phone'] ?? ''); ?>" placeholder="<?php echo e(__('Phone Number')); ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Country')); ?></label>
                    <select name="country" class="premium-input premium-input-select" disabled>
                        <option value=""><?php echo e(__('Select Country')); ?></option>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?php echo e($code); ?>" <?php if (($user['country'] ?? '') == $code) echo 'selected'; ?>><?php echo e($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-zinc-500 text-xs mt-1.5"><i class="fa-solid fa-lock me-1"></i> <?php echo e(__('Country cannot be changed')); ?></p>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors shadow-[0_0_20px_rgba(16,185,129,0.2)]"><?php echo e(__('Save Changes')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div id="modal-password" class="fixed inset-0 z-[60] flex items-center justify-center px-4 opacity-0 invisible transition-all duration-300">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeModal('modal-password')"></div>
    <div class="relative bg-brand-card border border-zinc-800/60 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-box">
        <div class="h-1 w-full bg-gradient-to-r from-amber-500/10 via-amber-500 to-amber-500/10"></div>

        <div class="p-6 overflow-y-auto max-h-[85vh]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white tracking-tight"><?php echo e(__('Security')); ?></h3>
                <button type="button" onclick="closeModal('modal-password')" class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="/actions/change-password.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Current Password')); ?></label>
                    <input type="password" name="current_password" class="premium-input" placeholder="<?php echo e(__('Current password')); ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('New Password')); ?></label>
                    <input type="password" name="new_password" class="premium-input" placeholder="<?php echo e(__('New password')); ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Confirm New Password')); ?></label>
                    <input type="password" name="confirm_password" class="premium-input" placeholder="<?php echo e(__('Confirm new password')); ?>">
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors shadow-[0_0_20px_rgba(16,185,129,0.2)]"><?php echo e(__('Update Password')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($kyc_enabled): ?>
    <!-- KYC Modal -->
    <div id="modal-kyc" class="fixed inset-0 z-[60] flex items-center justify-center px-4 opacity-0 invisible transition-all duration-300">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeModal('modal-kyc')"></div>
        <div class="relative bg-brand-card border border-zinc-800/60 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-box">
            <div class="h-1 w-full bg-gradient-to-r from-emerald-500/10 via-emerald-500 to-emerald-500/10"></div>

            <div class="p-6 overflow-y-auto max-h-[85vh]">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-white tracking-tight"><?php echo e(__('Identity Verification')); ?></h3>
                    <button type="button" onclick="closeModal('modal-kyc')" class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($kyc_status === 'approved'): ?>
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-emerald-500/10 text-emerald-500 text-xs font-semibold border border-emerald-500/20 mb-4">
                        <?php echo e(__('Verified')); ?>
                    </span>
                <?php elseif ($kyc_status === 'pending'): ?>
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-amber-500/10 text-amber-500 text-xs font-semibold border border-amber-500/20 mb-4">
                        <?php echo e(__('Pending')); ?>
                    </span>
                <?php elseif ($kyc_status === 'rejected'): ?>
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-rose-500/10 text-rose-500 text-xs font-semibold border border-rose-500/20 mb-4">
                        <?php echo e(__('Rejected')); ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-zinc-800/50 text-zinc-300 text-xs font-semibold border border-zinc-700/50 mb-4">
                        <?php echo e(__('Not Submitted')); ?>
                    </span>
                <?php endif; ?>

                <?php if ($kyc_status === 'pending' || $kyc_status === 'approved'): ?>
                    <?php
                    $doc_items = [];
                    if ($kyc) {
                        if ($kyc['id_passport_path']) {
                            $doc_items[] = ['type' => __('ID/Passport'), 'path' => $kyc['id_passport_path'], 'date' => $kyc['created_at'], 'status' => $kyc['status']];
                        }
                        if ($kyc['proof_address_path']) {
                            $doc_items[] = ['type' => __('Proof of Address'), 'path' => $kyc['proof_address_path'], 'date' => $kyc['created_at'], 'status' => $kyc['status']];
                        }
                        if ($kyc['selfie_path']) {
                            $doc_items[] = ['type' => __('Selfie with ID'), 'path' => $kyc['selfie_path'], 'date' => $kyc['created_at'], 'status' => $kyc['status']];
                        }
                    }
                    ?>
                    <h6 class="text-sm font-bold text-zinc-100 mb-4"><?php echo e(__('Uploaded Documents')); ?></h6>
                    <?php if (!empty($doc_items)): ?>
                        <div class="space-y-3 mb-4">
                            <?php foreach ($doc_items as $item):
                                $status_color = $item['status'] === 'approved' ? 'emerald' : ($item['status'] === 'rejected' ? 'rose' : 'amber');
                            ?>
                                <div class="flex items-center justify-between p-4 rounded-2xl bg-zinc-900/50 border border-zinc-800 hover:border-zinc-700/80 transition-all">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-10 h-10 rounded-xl bg-brand-accent/10 text-brand-accent flex items-center justify-center border border-brand-accent/20 shrink-0">
                                            <i class="fa-solid fa-file-image"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-zinc-100 font-semibold text-sm truncate"><?php echo e($item['type']); ?></p>
                                            <p class="text-zinc-500 text-xs"><?php echo e(format_date($item['date'])); ?></p>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold border bg-<?php echo $status_color; ?>-500/10 text-<?php echo $status_color; ?>-500 border-<?php echo $status_color; ?>-500/20 shrink-0 ml-3">
                                        <?php echo e(__(ucfirst($item['status']))); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-zinc-500 text-sm mb-4"><?php echo e(__('No documents uploaded yet.')); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="mb-5 rounded-xl border border-brand-accent/20 bg-brand-accent/10 px-4 py-4 text-brand-accent flex items-start gap-3" role="alert">
                        <i class="fa-solid fa-circle-info mt-0.5"></i>
                        <div class="text-sm">
                            <strong class="block mb-0.5"><?php echo e(__('Why verify?')); ?></strong>
                            <?php echo e(__('Verified users get higher withdrawal limits and priority support. Please upload clear photos of your government-issued ID.')); ?>
                        </div>
                    </div>

                    <form action="/actions/kyc-upload.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-2">
                            <div>
                                <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Government ID / Passport')); ?></label>
                                <div class="upload-zone" :class="selectedFiles.id_passport ? 'upload-zone-selected' : ''">
                                    <input type="file" name="id_passport" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*,application/pdf" @change="selectedFiles.id_passport = $event.target.files[0]?.name ?? null">
                                    <template x-if="!selectedFiles.id_passport">
                                        <div class="text-center">
                                            <i class="fa-solid fa-id-card text-2xl text-brand-accent mb-3 opacity-50"></i>
                                            <h6 class="text-zinc-100 font-semibold text-sm mb-1"><?php echo e(__('Click to Upload')); ?></h6>
                                            <p class="text-zinc-500 text-xs"><?php echo e(__('JPG, PNG or PDF, max 5MB')); ?></p>
                                        </div>
                                    </template>
                                    <template x-if="selectedFiles.id_passport">
                                        <div class="flex flex-col items-center text-emerald-500">
                                            <i class="fa-solid fa-circle-check text-2xl mb-2"></i>
                                            <strong class="text-sm" x-text="selectedFiles.id_passport.length > 36 ? selectedFiles.id_passport.slice(0,36) + '...' : selectedFiles.id_passport"></strong>
                                            <span class="text-zinc-500 text-xs"><?php echo e(__('Ready to upload')); ?></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Proof of Address')); ?></label>
                                <div class="upload-zone" :class="selectedFiles.proof_address ? 'upload-zone-selected' : ''">
                                    <input type="file" name="proof_address" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*,application/pdf" @change="selectedFiles.proof_address = $event.target.files[0]?.name ?? null">
                                    <template x-if="!selectedFiles.proof_address">
                                        <div class="text-center">
                                            <i class="fa-solid fa-file-invoice text-2xl text-brand-accent mb-3 opacity-50"></i>
                                            <h6 class="text-zinc-100 font-semibold text-sm mb-1"><?php echo e(__('Click to Upload')); ?></h6>
                                            <p class="text-zinc-500 text-xs"><?php echo e(__('Utility bill or bank statement')); ?></p>
                                        </div>
                                    </template>
                                    <template x-if="selectedFiles.proof_address">
                                        <div class="flex flex-col items-center text-emerald-500">
                                            <i class="fa-solid fa-circle-check text-2xl mb-2"></i>
                                            <strong class="text-sm" x-text="selectedFiles.proof_address.length > 36 ? selectedFiles.proof_address.slice(0,36) + '...' : selectedFiles.proof_address"></strong>
                                            <span class="text-zinc-500 text-xs"><?php echo e(__('Ready to upload')); ?></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Selfie with ID')); ?></label>
                                <div class="upload-zone" :class="selectedFiles.selfie ? 'upload-zone-selected' : ''">
                                    <input type="file" name="selfie" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*" @change="selectedFiles.selfie = $event.target.files[0]?.name ?? null">
                                    <template x-if="!selectedFiles.selfie">
                                        <div class="text-center">
                                            <i class="fa-solid fa-camera text-2xl text-brand-accent mb-3 opacity-50"></i>
                                            <h6 class="text-zinc-100 font-semibold text-sm mb-1"><?php echo e(__('Take a Selfie')); ?></h6>
                                            <p class="text-zinc-500 text-xs"><?php echo e(__('Hold your ID next to your face. Ensure good lighting.')); ?></p>
                                        </div>
                                    </template>
                                    <template x-if="selectedFiles.selfie">
                                        <div class="flex flex-col items-center text-emerald-500">
                                            <i class="fa-solid fa-circle-check text-2xl mb-2"></i>
                                            <strong class="text-sm" x-text="selectedFiles.selfie.length > 36 ? selectedFiles.selfie.slice(0,36) + '...' : selectedFiles.selfie"></strong>
                                            <span class="text-zinc-500 text-xs"><?php echo e(__('Ready to upload')); ?></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="pt-5 border-t border-zinc-800 flex justify-end">
                            <button type="submit" :disabled="!allSelected()" class="w-full sm:w-auto px-8 py-3.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors shadow-[0_0_20px_rgba(16,185,129,0.2)] disabled:opacity-50 disabled:cursor-not-allowed">
                                <?php echo e(__('Submit All Documents')); ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Help Center Modal -->
<div id="modal-help" class="fixed inset-0 z-[60] flex items-center justify-center px-4 opacity-0 invisible transition-all duration-300">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeModal('modal-help')"></div>
    <div class="relative bg-brand-card border border-zinc-800/60 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-box">
        <div class="h-1 w-full bg-gradient-to-r from-zinc-500/10 via-zinc-500 to-zinc-500/10"></div>

        <div class="p-6 overflow-y-auto max-h-[85vh]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white tracking-tight"><?php echo e(__('Contact Support')); ?></h3>
                <button type="button" onclick="closeModal('modal-help')" class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="/actions/contact-submit.php" method="POST" class="space-y-4" data-no-spinner>
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="website" value="">
                <input type="hidden" name="name" value="<?php echo e($user['name'] ?? ''); ?>">
                <input type="hidden" name="email" value="<?php echo e($user['email'] ?? ''); ?>">
                <input type="hidden" name="ajax" value="1">

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Subject')); ?></label>
                    <select name="subject" class="premium-input premium-input-select" required>
                        <option value=""><?php echo e(__('Select a subject')); ?></option>
                        <option value="<?php echo e(__('General Inquiry')); ?>"><?php echo e(__('General Inquiry')); ?></option>
                        <option value="<?php echo e(__('Investment Issue')); ?>"><?php echo e(__('Investment Issue')); ?></option>
                        <option value="<?php echo e(__('Deposit / Withdrawal')); ?>"><?php echo e(__('Deposit / Withdrawal')); ?></option>
                        <option value="<?php echo e(__('Account Access')); ?>"><?php echo e(__('Account Access')); ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5 uppercase tracking-wide"><?php echo e(__('Message')); ?></label>
                    <textarea name="message" rows="4" class="premium-input resize-none" placeholder="<?php echo e(__('Describe your issue in detail...')); ?>" required></textarea>
                </div>

                <div id="help-status" class="hidden rounded-lg px-4 py-3 text-sm mb-4"></div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors shadow-[0_0_20px_rgba(16,185,129,0.2)] flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> <?php echo e(__('Send Message')); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require ROOT . '/includes/new-footer.php'; ?>
