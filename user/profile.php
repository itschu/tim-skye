<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Account Settings');
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
?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Account Settings'); ?></h3>
            <p class="text-secondary mb-0 small"><?php echo __('Manage your profile, security and verification'); ?></p>
        </div>
    </div>

    <a href="/contact" class="btn btn-white border shadow-sm rounded-pill px-3 py-2 small fw-bold text-secondary">
        <i class="fas fa-headset me-2"></i><?php echo __('Contact Support'); ?>
    </a>
</div>

<!-- Alpine.js Component -->
<div class="row g-4" x-data="{ 
    activeTab: 'profile',
    kycStatus: '<?php echo $kyc_status; ?>',
    profilePreview: '<?php echo e($profile_picture_url ? '/' . $profile_picture_url : ''); ?>',
    defaultAvatarUrl: 'https://ui-avatars.com/api/?name=<?php echo urlencode($user['name'] ?? 'User'); ?>&background=4f46e5&color=fff',
    selectedFiles: { id_passport: null, proof_address: null, selfie: null },
    switchTab(tabName) {
        this.activeTab = tabName;
        if (window.innerWidth < 992) {
            this.$nextTick(() => {
                const el = document.getElementById('tab-' + tabName);
                if (el) {
                    setTimeout(() => {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            });
        }
    },
    allSelected() {
        return !!(this.selectedFiles.id_passport && this.selectedFiles.proof_address && this.selectedFiles.selfie);
    },
    previewImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.profilePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
}"
    x-init="if (window.location.hash === '#kyc') activeTab = 'kyc'">
    <!-- Left Sidebar -->
    <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="card border-0 shadow-sm mb-4 overflow-hidden" style="border-radius: 1.25rem;">
            <div class="profile-cover position-relative">
                <!-- Decorative circles -->
                <div class="position-absolute" style="top: -50%; right: -10%; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none;"></div>
                <div class="position-absolute" style="bottom: -30%; left: -5%; width: 150px; height: 150px; background: rgba(255,255,255,0.08); border-radius: 50%; pointer-events: none;"></div>
            </div>

            <div class="card-body p-0 text-center pb-4">
                <div class="position-relative d-inline-block">
                    <img x-bind:src="profilePreview || defaultAvatarUrl"
                        class="profile-avatar"
                        alt="<?php echo e($user['name'] ?? 'User'); ?>" />
                    <button class="btn btn-sm btn-dark rounded-circle position-absolute bottom-0 end-0 shadow-sm border border-2 border-white"
                        style="width: 32px; height: 32px; padding: 0; right: 0; bottom: 5px"
                        onclick="document.getElementById('avatarUpload').click()">
                        <i class="fas fa-camera small text-white"></i>
                    </button>
                </div>

                <div class="mt-3 px-3">
                    <h5 class="fw-bold text-dark mb-1"><?php echo e($user['name'] ?? 'User'); ?></h5>
                    <p class="text-secondary small mb-3"><?php echo e($user['email'] ?? ''); ?></p>

                    <?php if ($kyc_enabled): ?>
                        <div class="d-flex justify-content-center gap-2">
                            <?php if ($kyc_status === 'approved'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 rounded-pill px-3 py-2">
                                    <i class="fas fa-check-circle me-1"></i> <?php echo __('Verified'); ?>
                                </span>
                            <?php elseif ($kyc_status === 'pending'): ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 rounded-pill px-3 py-2">
                                    <i class="fas fa-clock me-1"></i> <?php echo __('KYC Pending'); ?>
                                </span>
                            <?php elseif ($kyc_status === 'rejected'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 rounded-pill px-3 py-2">
                                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo __('Failed'); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 rounded-pill px-3 py-2">
                                    <i class="fas fa-user me-1"></i> <?php echo __('Not Verified'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Settings Navigation -->
        <div class="card border-0 shadow-sm" style="border-radius: 1.25rem;">
            <div class="card-body p-2 settings-nav d-flex flex-column gap-1">
                <button class="btn-nav" :class="{ 'active': activeTab === 'profile' }" @click="switchTab('profile')">
                    <span><i class="fas fa-user-circle me-3 opacity-50"></i> <?php echo __('Personal Details'); ?></span>
                    <i class="fas fa-chevron-right small opacity-50"></i>
                </button>

                <button class="btn-nav" :class="{ 'active': activeTab === 'security' }" @click="switchTab('security')">
                    <span><i class="fas fa-shield-alt me-3 opacity-50"></i> <?php echo __('Security'); ?></span>
                    <i class="fas fa-chevron-right small opacity-50"></i>
                </button>

                <?php if ($kyc_enabled): ?>
                    <button class="btn-nav" :class="{ 'active': activeTab === 'kyc' }" @click="switchTab('kyc')">
                        <span><i class="fas fa-id-card me-3 opacity-50"></i> <?php echo __('KYC Verification'); ?></span>
                        <div class="d-flex align-items-center">
                            <?php if ($kyc_status !== 'approved'): ?>
                                <span class="bg-danger rounded-circle d-inline-block me-2" style="width: 8px; height: 8px"></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-right small opacity-50"></i>
                        </div>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Content -->
    <div class="col-lg-8 position-relative" style="min-height: 500px;">
        <!-- Profile Tab -->
        <div id="tab-profile" x-show="activeTab === 'profile'"
            x-transition:enter="tab-enter"
            x-transition:enter-start="tab-enter-start"
            x-transition:enter-end="tab-enter-end"
            x-transition:leave="tab-leave"
            x-transition:leave-start="tab-leave-start"
            x-transition:leave-end="tab-leave-end">
            <div class="card border-0 shadow-sm" style="border-radius: 1.25rem;">
                <div class="card-header bg-transparent border-bottom p-4">
                    <h5 class="fw-bold mb-0"><?php echo __('Personal Information'); ?></h5>
                </div>
                <div class="card-body p-4">
                    <form action="/actions/profile-update.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="file" id="avatarUpload" name="profile_picture" class="d-none" accept="image/*" @change="previewImage($event)">

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2"><?php echo __('Full Name'); ?></label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($user['name'] ?? ''); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2"><?php echo __('Email Address'); ?></label>
                                <input type="email" class="form-control bg-light text-muted" value="<?php echo e($user['email'] ?? ''); ?>" readonly />
                                <div class="form-text small mt-1"><i class="fas fa-lock me-1"></i> <?php echo __('Email cannot be changed'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2"><?php echo __('Phone Number'); ?></label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo e($user['phone'] ?? ''); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2"><?php echo __('Country'); ?></label>
                                <select name="country" class="form-select" disabled>
                                    <option value=""><?php echo __('Select Country'); ?></option>
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?php echo e($code); ?>" <?php if (($user['country'] ?? '') == $code) echo 'selected'; ?>><?php echo e($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small mt-1"><i class="fas fa-lock me-1"></i> <?php echo __('Country cannot be changed'); ?></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><?php echo __('Save Changes'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="tab-security" x-show="activeTab === 'security'"
            x-transition:enter="tab-enter"
            x-transition:enter-start="tab-enter-start"
            x-transition:enter-end="tab-enter-end"
            x-transition:leave="tab-leave"
            x-transition:leave-start="tab-leave-start"
            x-transition:leave-end="tab-leave-end"
            style="display: none">
            <div class="card border-0 shadow-sm" style="border-radius: 1.25rem;">
                <div class="card-header bg-transparent border-bottom p-4">
                    <h5 class="fw-bold mb-0"><?php echo __('Security Settings'); ?></h5>
                </div>
                <div class="card-body p-4">
                    <form action="/actions/change-password.php" method="POST" class="mb-5">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                        <h6 class="fw-bold text-dark mb-3"><?php echo __('Change Password'); ?></h6>
                        <div class="mb-3">
                            <label class="small fw-bold text-secondary mb-2"><?php echo __('Current Password'); ?></label>
                            <input type="password" name="current_password" class="form-control" placeholder="••••••••" />
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2"><?php echo __('New Password'); ?></label>
                                <input type="password" name="new_password" class="form-control" placeholder="<?php echo __('New password'); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2"><?php echo __('Confirm Password'); ?></label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="<?php echo __('Confirm new password'); ?>" />
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><?php echo __('Update Password'); ?></button>
                        </div>
                    </form>

                    <hr class="text-secondary opacity-10 my-4" />

                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                        <div>
                            <h6 class="fw-bold mb-1"><?php echo __('Two-Factor Authentication (2FA)'); ?></h6>
                            <p class="text-secondary small mb-0"><?php echo __('Add an extra layer of security to your account.'); ?></p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="2faSwitch" style="width: 3rem; height: 1.5rem" disabled />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($kyc_enabled): ?>
            <!-- KYC Tab -->
            <div id="tab-kyc" x-show="activeTab === 'kyc'"
                x-transition:enter="tab-enter"
                x-transition:enter-start="tab-enter-start"
                x-transition:enter-end="tab-enter-end"
                x-transition:leave="tab-leave"
                x-transition:leave-start="tab-leave-start"
                x-transition:leave-end="tab-leave-end"
                style="display: none">
                <div class="card border-0 shadow-sm" style="border-radius: 1.25rem;">
                    <div class="card-header bg-transparent border-bottom p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0"><?php echo __('Identity Verification'); ?></h5>
                            <?php if ($kyc_status === 'approved'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><?php echo __('Verified'); ?></span>
                            <?php elseif ($kyc_status === 'pending'): ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill"><?php echo __('Pending'); ?></span>
                            <?php elseif ($kyc_status === 'rejected'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><?php echo __('Rejected'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill"><?php echo __('Not Submitted'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($kyc_status === 'pending' || $kyc_status === 'approved'): ?>
                            <!-- Pending/Approved: show uploaded documents -->
                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-3"><?php echo __('Uploaded Documents'); ?></h6>
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
                                <?php if (!empty($doc_items)): ?>
                                    <div class="list-group">
                                        <?php foreach ($doc_items as $item): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="bg-light rounded p-2">
                                                        <i class="fas fa-file-image text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold"><?php echo e($item['type']); ?></span>
                                                        <div class="small text-muted"><?php echo format_date($item['date']); ?></div>
                                                    </div>
                                                </div>
                                                <span class="badge <?php echo $item['status'] === 'approved' ? 'bg-success' : ($item['status'] === 'rejected' ? 'bg-danger' : 'bg-warning'); ?> rounded-pill">
                                                    <?php echo __(ucfirst($item['status'])); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted"><?php echo __('No documents uploaded yet.'); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Not Submitted/Rejected: show single upload form -->
                            <div class="mb-4">
                                <div class="alert alert-primary bg-primary bg-opacity-10 border-0 d-flex align-items-start gap-3 mb-4 rounded-3">
                                    <i class="fas fa-info-circle mt-1 text-primary"></i>
                                    <div class="small text-primary-emphasis">
                                        <strong><?php echo __('Why verify?'); ?></strong>
                                        <?php echo __('Verified users get higher withdrawal limits and priority support. Please upload clear photos of your government-issued ID.'); ?>
                                    </div>
                                </div>

                                <form action="/actions/kyc-upload.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-secondary mb-2"><?php echo __('Government ID / Passport'); ?></label>
                                            <div class="upload-zone position-relative" :class="selectedFiles.id_passport ? 'upload-zone-selected' : ''">
                                                <input type="file" name="id_passport" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer" accept="image/*,application/pdf" @change="selectedFiles.id_passport = $event.target.files[0]?.name ?? null" />
                                                <template x-if="!selectedFiles.id_passport">
                                                    <div class="text-center">
                                                        <i class="fas fa-id-card fa-2x text-primary mb-3 opacity-50"></i>
                                                        <h6 class="fw-bold small mb-1"><?php echo __('Click to Upload'); ?></h6>
                                                        <p class="text-muted small mb-0" style="font-size: 0.75rem;"><?php echo __('JPG, PNG or PDF, max 5MB'); ?></p>
                                                    </div>
                                                </template>
                                                <template x-if="selectedFiles.id_passport">
                                                    <div class="d-flex flex-column align-items-center text-success">
                                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                        <strong x-text="selectedFiles.id_passport.length > 36 ? selectedFiles.id_passport.slice(0,36) + '...' : selectedFiles.id_passport"></strong>
                                                        <div class="small text-muted"><?php echo __('Ready to upload'); ?></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="small fw-bold text-secondary mb-2"><?php echo __('Proof of Address'); ?></label>
                                            <div class="upload-zone position-relative" :class="selectedFiles.proof_address ? 'upload-zone-selected' : ''">
                                                <input type="file" name="proof_address" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer" accept="image/*,application/pdf" @change="selectedFiles.proof_address = $event.target.files[0]?.name ?? null" />
                                                <template x-if="!selectedFiles.proof_address">
                                                    <div class="text-center">
                                                        <i class="fas fa-file-alt fa-2x text-primary mb-3 opacity-50"></i>
                                                        <h6 class="fw-bold small mb-1"><?php echo __('Click to Upload'); ?></h6>
                                                        <p class="text-muted small mb-0" style="font-size: 0.75rem;"><?php echo __('Utility bill or bank statement'); ?></p>
                                                    </div>
                                                </template>
                                                <template x-if="selectedFiles.proof_address">
                                                    <div class="d-flex flex-column align-items-center text-success">
                                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                        <strong x-text="selectedFiles.proof_address.length > 36 ? selectedFiles.proof_address.slice(0,36) + '...' : selectedFiles.proof_address"></strong>
                                                        <div class="small text-muted"><?php echo __('Ready to upload'); ?></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="small fw-bold text-secondary mb-2"><?php echo __('Selfie with ID'); ?></label>
                                            <div class="upload-zone position-relative" :class="selectedFiles.selfie ? 'upload-zone-selected' : ''">
                                                <input type="file" name="selfie" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer" accept="image/*" @change="selectedFiles.selfie = $event.target.files[0]?.name ?? null" />
                                                <template x-if="!selectedFiles.selfie">
                                                    <div class="text-center">
                                                        <i class="fas fa-camera fa-2x text-primary mb-3 opacity-50"></i>
                                                        <h6 class="fw-bold small mb-1"><?php echo __('Take a Selfie'); ?></h6>
                                                        <p class="text-muted small mb-0" style="font-size: 0.75rem;"><?php echo __('Hold your ID next to your face. Ensure good lighting.'); ?></p>
                                                    </div>
                                                </template>
                                                <template x-if="selectedFiles.selfie">
                                                    <div class="d-flex flex-column align-items-center text-success">
                                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                        <strong x-text="selectedFiles.selfie.length > 36 ? selectedFiles.selfie.slice(0,36) + '...' : selectedFiles.selfie"></strong>
                                                        <div class="small text-muted"><?php echo __('Ready to upload'); ?></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end pt-4 border-top mt-4">
                                        <button type="submit" :disabled="!allSelected()" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><?php echo __('Submit All Documents'); ?></button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Profile Cover */
    .profile-cover {
        height: 120px;
        background: var(--gradient-card);
        position: relative;
    }

    /* Profile Avatar */
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        object-fit: cover;
        margin-top: -50px;
        background: white;
        position: relative;
    }

    /* Settings Navigation */
    .settings-nav .btn-nav {
        width: 100%;
        text-align: left;
        padding: 1rem 1.25rem;
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-weight: 600;
        border-radius: 0.75rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .settings-nav .btn-nav:hover {
        background-color: #f8fafc;
        color: var(--primary);
    }

    .settings-nav .btn-nav.active {
        background-color: rgba(79, 70, 229, 0.1);
        color: var(--primary);
    }

    /* Upload Zone */
    .upload-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
        transition: all 0.2s;
        background: #f8fafc;
        cursor: pointer;
    }

    .upload-zone:hover {
        border-color: var(--primary);
        background: rgba(79, 70, 229, 0.02);
    }

    .upload-zone-selected {
        border-style: solid !important;
        border-width: 2px !important;
        border-color: #10b981 !important;
        /* success */
        background: rgba(16, 185, 129, 0.04) !important;
    }

    /* Smooth Tab Transitions */
    .tab-enter {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .tab-enter-start {
        opacity: 0;
        transform: translateX(20px);
    }

    .tab-enter-end {
        opacity: 1;
        transform: translateX(0);
    }

    .tab-leave {
        transition: all 0.2s cubic-bezier(0.4, 0, 1, 1);
        position: absolute;
        width: 100%;
        top: 0;
        left: 0;
    }

    .tab-leave-start {
        opacity: 1;
        transform: translateX(0);
    }

    .tab-leave-end {
        opacity: 0;
        transform: translateX(-20px);
    }

    /* Form styling */
    .form-control,
    .form-select {
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        font-size: 0.95rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
</style>

<?php require ROOT . '/includes/footer.php'; ?>
</body>

</html>