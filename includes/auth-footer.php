<style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #0B0F19;
    }

    ::-webkit-scrollbar-thumb {
        background: #374151;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #8b5cf6;
    }
</style>

<?php
// Inject instant messaging/chat snippet if set
$im = get_setting('instant_message_code', '');
if (!empty($im)) {
    echo $im;
}
