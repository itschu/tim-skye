<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$preloaded_user = null;
if (!empty($_GET['user_id'])) {
    $uid = intval($_GET['user_id']);
    $result = db_query("SELECT id, name, email, profile_picture FROM users WHERE id = ?", [$uid]);
    if (!empty($result)) {
        $u = $result[0];
        $parts = explode(' ', trim($u['name']), 2);
        $preloaded_user = [
            'id' => $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'profile_picture' => $u['profile_picture'] ?? null,
            'first_name' => $parts[0] ?? $u['name'],
            'last_name' => $parts[1] ?? '',
            'full_name' => $u['name'],
            'balance' => '0.00'
        ];
    }
}

$page_title = __('Internal Messaging');
require_once ROOT . '/includes/admin-header.php';
?>

<script>
    window._inmailPreloaded = <?php echo $preloaded_user ? json_encode([$preloaded_user], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]'; ?>;
</script>

<div class="container-fluid p-3 p-md-4" x-data="inmailSystem()" x-cloak>
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><?php echo __('Internal Messaging'); ?></h4>
            <p class="text-secondary small mb-0"><?php echo __('Send notifications and updates to user groups.'); ?></p>
        </div>
    </div>

    <!-- Status Alert -->
    <template x-if="sendStatus && sendStatus !== ''" x-cloak>
        <div x-transition
            :class="[
                'alert border d-flex align-items-center gap-3 mb-4',
                sendStatus === 'success' 
                    ? 'bg-success bg-opacity-10 border-success border-opacity-25 text-success' 
                    : 'bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger'
            ]">
            <i :class="sendStatus === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation'" class="fas flex-shrink-0"></i>
            <span x-text="sendMessage" class="flex-grow-1"></span>
            <button type="button" class="btn-close ms-auto" :style="sendStatus === 'success' ? 'filter: brightness(1.5)' : ''" @click="sendStatus = ''"></button>
        </div>
    </template>

    <div class="row g-4">
        <!-- Left Column - Recipients -->
        <div class="col-lg-5 col-xl-4">
            <div class="card bg-card border-subtle h-100 d-flex flex-column">
                <div class="card-header bg-transparent border-bottom border-subtle p-3">
                    <h6 class="mb-0 text-white fs-7 fw-bold text-uppercase ls-1">1. <?php echo __('Select Recipients'); ?></h6>
                </div>

                <div class="card-body p-3 d-flex flex-column flex-grow-1">
                    <!-- Search Box -->
                    <div class="position-relative mb-3">
                        <div style="position: relative;">
                            <i class="fa-solid fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; color: var(--text-muted);"></i>
                            <input
                                type="text"
                                class="form-control form-control-custom ps-5"
                                placeholder="<?php echo __('Search users...'); ?>"
                                x-model="searchQuery"
                                @input="onSearchInput"
                                autocomplete="off">

                            <!-- Loading Indicator -->
                            <div x-show="isSearching" x-cloak style="position: absolute; top: 50%; right: 12px; transform: translateY(-50%);">
                                <div class="spinner-border spinner-border-sm text-primary" role="status" style="width: 16px; height: 16px; border-width: 2px;">
                                    <span class="visually-hidden"><?php echo __('Loading...'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results Dropdown -->
                        <div x-show="searchResults.length > 0" x-cloak x-transition
                            @click.outside="searchResults = []"
                            class="search-dropdown">
                            <!-- Header Row -->
                            <div class="p-3 border-bottom border-subtle d-flex justify-content-between align-items-center sticky-top" style="background-color: var(--bg-card);">
                                <small class="text-muted">
                                    <?php echo __('Found'); ?> <span x-text="searchResults.length"></span> <?php echo __('results'); ?>
                                </small>
                                <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none" @click="selectAllResults()">
                                    <?php echo __('Select All'); ?>
                                </button>
                            </div>

                            <!-- Results List -->
                            <div>
                                <template x-for="user in searchResults" :key="user.id">
                                    <div @click="selectUser(user)"
                                        class="p-3 border-bottom border-subtle bg-hover"
                                        style="cursor: pointer; transition: background-color 0.2s;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                style="width: 36px; height: 36px; background-color: rgba(16, 185, 129, 0.1);">
                                                <span class="text-success fw-bold" x-text="user.name ? user.name.charAt(0).toUpperCase() : '?'"></span>
                                            </div>
                                            <div class="flex-grow-1 min-width-0">
                                                <div class="fw-bold text-white small" x-text="user.name"></div>
                                                <div class="text-muted small text-truncate" x-text="user.email"></div>
                                            </div>
                                            <i class="fa-solid fa-plus text-muted flex-shrink-0" style="font-size: 0.875rem;"></i>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Users Count and Clear -->
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <span x-text="selectedUsers.length"></span> <?php echo __('selected'); ?>
                        </small>
                        <button x-show="selectedUsers.length > 0" x-cloak
                            type="button"
                            @click="clearAllUsers()"
                            class="btn btn-sm btn-link text-danger p-0 text-decoration-none">
                            <?php echo __('Clear All'); ?>
                        </button>
                    </div>

                    <!-- Recipients Container -->
                    <div class="flex-grow-1 overflow-y-auto border border-subtle rounded bg-black p-2" style="min-height: 300px; max-height: 500px;">
                        <template x-if="selectedUsers.length === 0">
                            <div class="text-center text-muted d-flex flex-column align-items-center justify-content-center h-100">
                                <i class="fa-solid fa-users" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                                <p class="small mb-0"><?php echo __('No recipients selected'); ?></p>
                            </div>
                        </template>

                        <template x-if="selectedUsers.length > 0">
                            <div class="d-flex flex-column gap-2">
                                <template x-for="user in selectedUsers.filter(u => u && u.id)" :key="user.id">
                                    <div class="recipient-item rounded p-2 d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2 min-width-0">
                                            <template x-if="user.profile_picture">
                                                <img :src="'/' + user.profile_picture" :alt="user.name" class="rounded-circle flex-shrink-0" style="width: 28px; height: 28px; object-fit: cover;">
                                            </template>
                                            <template x-if="!user.profile_picture">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width: 28px; height: 28px; background-color: rgba(16, 185, 129, 0.1);">
                                                    <span class="text-success fw-bold small" x-text="user.name ? user.name.charAt(0).toUpperCase() : '?'"></span>
                                                </div>
                                            </template>
                                            <div class="min-width-0">
                                                <div class="fw-bold text-white small text-truncate" x-text="user.name || 'Unknown'"></div>
                                                <div class="text-muted small text-truncate" style="font-size: 0.75rem;" x-text="user.email || ''"></div>
                                            </div>
                                        </div>
                                        <button type="button" @click="removeUser(user.id)"
                                            class="btn btn-sm btn-link text-danger p-0 flex-shrink-0"
                                            style="line-height: 1;">
                                            <i class="fa-solid fa-x" style="font-size: 0.875rem;"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <!-- Add All Users Button -->
                    <div class="mt-3">
                        <button type="button" @click="selectAllFromSearch()"
                            class="btn btn-outline-secondary border-subtle text-white w-100 btn-sm"
                            :disabled="isSearching">
                            <i class="fa-solid fa-users-viewfinder me-1"></i><?php echo __('Add All Users'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Compose Message -->
        <div class="col-lg-7 col-xl-8">
            <div class="card bg-card border-subtle h-100 d-flex flex-column">
                <div class="card-header bg-transparent border-bottom border-subtle p-3">
                    <h6 class="mb-0 text-white fs-7 fw-bold text-uppercase ls-1">2. <?php echo __('Compose Message'); ?></h6>
                </div>

                <div class="card-body p-4 d-flex flex-column flex-grow-1">
                    <form @submit.prevent="sendMessageAction" class="d-flex flex-column flex-grow-1" data-no-spinner>
                        <!-- Subject -->
                        <div class="mb-3">
                            <label class="form-label text-white small fw-bold"><?php echo __('Subject Line'); ?></label>
                            <input type="text"
                                class="form-control form-control-custom"
                                x-model="subject"
                                placeholder="<?php echo __('Enter message subject...'); ?>"
                                autocomplete="off">
                        </div>

                        <!-- Message -->
                        <div class="mb-3 flex-grow-1 d-flex flex-column">
                            <label class="form-label text-white small fw-bold"><?php echo __('Message Body'); ?></label>
                            <textarea class="form-control form-control-custom font-mono flex-grow-1"
                                x-model="message"
                                rows="12"
                                placeholder="<?php echo __('Type your message here...'); ?>"
                                style="resize: none;"></textarea>
                        </div>

                        <!-- Insert Variables -->
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-2"><?php echo __('Insert Variables:'); ?></label>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="placeholder-chip" @click="insertVariable('{{user_name}}')">{{user_name}}</span>
                                <span class="placeholder-chip" @click="insertVariable('{{email}}')">{{email}}</span>
                                <span class="placeholder-chip" @click="insertVariable('{{first_name}}')">{{first_name}}</span>
                                <span class="placeholder-chip" @click="insertVariable('{{last_name}}')">{{last_name}}</span>
                                <span class="placeholder-chip" @click="insertVariable('{{full_name}}')">{{full_name}}</span>
                                <span class="placeholder-chip" @click="insertVariable('{{balance}}')">{{balance}}</span>
                                <span class="placeholder-chip" @click="insertVariable('{{site_name}}')">{{site_name}}</span>
                            </div>
                        </div>

                        <!-- Footer Row -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top border-subtle">
                            <div class="d-flex align-items-center gap-2 text-warning small">
                                <i class="fa-solid fa-circle-info"></i>
                                <small>
                                    <?php
                                    // Check if we're in development mode (localhost)
                                    $is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) ||
                                        strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
                                    if ($is_local):
                                    ?>
                                        <?php echo __('Running on localhost - emails logged to files'); ?>
                                    <?php else: ?>
                                        <?php echo __('Messages sent via email'); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <button type="submit"
                                class="btn btn-primary px-4 fw-medium"
                                :disabled="isSending || selectedUsers.length === 0">
                                <template x-if="!isSending">
                                    <span>
                                        <i class="fa-solid fa-paper-plane me-2"></i><?php echo __('Send Message'); ?>
                                    </span>
                                </template>
                                <template x-if="isSending">
                                    <span>
                                        <span class="spinner-border spinner-border-sm me-2" role="status" style="width: 16px; height: 16px;"></span>
                                        <?php echo __('Sending...'); ?>
                                    </span>
                                </template>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] {
        display: none !important;
    }
</style>

<script>
    function inmailSystem() {
        return {
            searchQuery: '',
            searchResults: [],
            selectedUsers: window._inmailPreloaded ?? [],
            isSearching: false,
            searchTimeout: null,
            subject: '',
            message: '',
            isSending: false,
            sendStatus: '',
            sendMessage: '',

            performSearch() {
                if (this.searchQuery.length < 2) {
                    this.searchResults = [];
                    return;
                }

                this.isSearching = true;

                fetch(`/admin/actions/search-users?q=${encodeURIComponent(this.searchQuery)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Filter valid users and exclude already selected
                        const validUsers = (data.users || []).filter(u => u && u.id);
                        const selectedIds = this.selectedUsers.map(u => u.id);
                        this.searchResults = validUsers.filter(u => !selectedIds.includes(u.id));
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        this.searchResults = [];
                    })
                    .finally(() => {
                        this.isSearching = false;
                    });
            },

            onSearchInput() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => this.performSearch(), 300);
            },

            selectUser(user) {
                // Prevent adding the same user twice
                if (this.selectedUsers.find(u => u.id === user.id)) {
                    this.searchQuery = '';
                    this.searchResults = [];
                    return;
                }

                this.selectedUsers = [...this.selectedUsers, user];
                this.searchQuery = '';
                this.searchResults = [];
            },

            removeUser(userId) {
                if (!userId) return;
                this.selectedUsers = this.selectedUsers.filter(u => u && u.id !== userId);
            },

            selectAllResults() {
                const validResults = this.searchResults.filter(user => user && user.id);
                const newUsers = validResults.filter(user => !this.selectedUsers.find(u => u && u.id === user.id));
                this.selectedUsers = [...this.selectedUsers, ...newUsers];
                this.searchQuery = '';
                this.searchResults = [];
            },

            clearAllUsers() {
                this.selectedUsers = [];
            },

            async selectAllFromSearch() {
                this.isSearching = true;
                try {
                    const response = await fetch(`/admin/actions/search-users?all=1`);
                    const data = await response.json();
                    const validUsers = (data.users || []).filter(u => u && u.id);
                    const selectedIds = this.selectedUsers.map(u => u.id);
                    const newUsers = validUsers.filter(u => !selectedIds.includes(u.id));
                    this.selectedUsers = [...this.selectedUsers, ...newUsers];
                } catch (error) {
                    console.error('Error loading all users:', error);
                } finally {
                    this.isSearching = false;
                }
            },

            insertVariable(text) {
                this.message += ' ' + text;
            },

            async sendMessageAction() {
                this.sendStatus = '';
                this.sendMessage = '';

                if (!Array.isArray(this.selectedUsers) || this.selectedUsers.length === 0) {
                    this.sendStatus = 'error';
                    this.sendMessage = <?php echo json_encode(__('Please select at least one recipient')); ?>;
                    return;
                }

                if (!this.subject.trim()) {
                    this.sendStatus = 'error';
                    this.sendMessage = <?php echo json_encode(__('Please enter a subject')); ?>;
                    return;
                }

                if (!this.message.trim()) {
                    this.sendStatus = 'error';
                    this.sendMessage = <?php echo json_encode(__('Please enter a message')); ?>;
                    return;
                }

                this.isSending = true;

                // Build unique recipients list (dedupe by id and filter valid users only)
                const seen = new Set();
                const uniqueRecipients = [];
                for (const u of this.selectedUsers) {
                    if (u && u.id && u.email && !seen.has(u.id)) {
                        seen.add(u.id);
                        uniqueRecipients.push({
                            id: u.id,
                            email: u.email,
                            name: u.name,
                            first_name: u.first_name,
                            last_name: u.last_name,
                            full_name: u.full_name,
                            balance: u.balance
                        });
                    }
                }

                if (uniqueRecipients.length === 0) {
                    this.isSending = false;
                    this.sendStatus = 'error';
                    this.sendMessage = <?php echo json_encode(__('Please select at least one valid recipient')); ?>;
                    return;
                }

                const formData = new FormData();
                formData.append('csrf_token', '<?php echo csrf_token(); ?>');
                formData.append('subject', this.subject);
                formData.append('message', this.message);
                formData.append('recipients', JSON.stringify(uniqueRecipients));

                try {
                    const response = await fetch('/admin/actions/send-in-mail', {
                        method: 'POST',
                        body: formData
                    });

                    const text = await response.text();
                    let data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (e) {
                        console.error('Invalid JSON from send-in-mail:', e, text);
                    }

                    if (data && typeof data.success !== 'undefined') {
                        this.sendStatus = data.success ? 'success' : 'error';
                        this.sendMessage = data.message || <?php echo json_encode(__('An error occurred while sending the message')); ?>;

                        if (data.success) {
                            // Clear form after successful send
                            this.subject = '';
                            this.message = '';
                            this.selectedUsers = [];
                        }
                    } else {
                        // Non-JSON or unexpected response — show server text if available
                        this.sendStatus = 'error';
                        this.sendMessage = text || <?php echo json_encode(__('An error occurred while sending the message')); ?>;
                    }
                } catch (error) {
                    console.error('Send request failed:', error);
                    this.sendStatus = 'error';
                    this.sendMessage = <?php echo json_encode(__('An error occurred while sending the message')); ?>;
                } finally {
                    this.isSending = false;
                }
            }
        };
    }
</script>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>