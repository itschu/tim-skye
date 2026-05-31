<?php
/**
 * View "Sent" Emails (for local development)
 * When running on localhost, emails are logged to files instead of being sent.
 * Use this page to view those logged emails.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/db.php';

$log_dir = ROOT . '/logs/emails';
$emails = [];

if (is_dir($log_dir)) {
    $files = glob($log_dir . '/email_*.html');
    // Sort by newest first
    rsort($files);
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Parse the header comments
        $to = 'Unknown';
        $subject = 'Unknown';
        $date = 'Unknown';
        
        if (preg_match('/To: (.+)/', $content, $m)) $to = $m[1];
        if (preg_match('/Subject: (.+)/', $content, $m)) $subject = $m[1];
        if (preg_match('/Date: (.+)/', $content, $m)) $date = $m[1];
        
        // Get the HTML body (after the comment block)
        $body = preg_replace('/<!--.*?-->/s', '', $content, 1);
        $body = trim($body);
        
        $emails[] = [
            'file' => basename($file),
            'to' => $to,
            'subject' => $subject,
            'date' => $date,
            'body' => $body
        ];
    }
}

$view = $_GET['view'] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sent Emails (Local Development)</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .email-item { background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .email-meta { color: #666; font-size: 14px; margin-bottom: 10px; }
        .email-subject { font-weight: bold; font-size: 16px; margin-bottom: 5px; }
        .email-preview { border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px; max-height: 200px; overflow: auto; background: #f9f9f9; padding: 10px; }
        .btn { display: inline-block; padding: 8px 16px; background: #4f46e5; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #4338ca; }
        .empty { text-align: center; padding: 50px; color: #666; }
        iframe { width: 100%; height: 500px; border: 1px solid #ddd; background: white; }
        .back-link { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📧 Sent Emails (Local Development)</h1>
        <p>On localhost, emails are logged to files instead of being sent via SMTP. This page shows those logged emails.</p>
        <div class="back-link">
            <a href="/admin/in-mail" class="btn">← Back to In-Mail</a>
            <a href="?" class="btn" style="background: #059669;">Refresh</a>
        </div>
    </div>
    
    <?php if ($view): ?>
        <?php 
        $email = null;
        foreach ($emails as $e) {
            if ($e['file'] === $view) {
                $email = $e;
                break;
            }
        }
        ?>
        <?php if ($email): ?>
            <div class="email-item">
                <div class="email-meta">
                    <strong>To:</strong> <?php echo htmlspecialchars($email['to']); ?> | 
                    <strong>Date:</strong> <?php echo htmlspecialchars($email['date']); ?>
                </div>
                <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                <iframe srcdoc="<?php echo htmlspecialchars($email['body']); ?>"></iframe>
            </div>
        <?php else: ?>
            <div class="empty">Email not found.</div>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($emails)): ?>
            <div class="empty">
                <h3>No emails logged yet</h3>
                <p>Send an email via In-Mail to see it here.</p>
            </div>
        <?php else: ?>
            <p><strong><?php echo count($emails); ?></strong> email(s) logged</p>
            <?php foreach ($emails as $email): ?>
                <div class="email-item">
                    <div class="email-meta">
                        <strong>To:</strong> <?php echo htmlspecialchars($email['to']); ?> | 
                        <strong>Date:</strong> <?php echo htmlspecialchars($email['date']); ?>
                    </div>
                    <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                    <div class="email-preview">
                        <?php echo substr(strip_tags($email['body']), 0, 200); ?>...
                    </div>
                    <p><a href="?view=<?php echo urlencode($email['file']); ?>" class="btn">View Full Email</a></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
