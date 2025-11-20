<?php
/**
 * License Status Banner
 * Shows trial/paid status in admin panel
 */

require_once __DIR__ . '/../config/config.php';

// Only show if license checking is enabled
if (!LICENSE_CHECK_ENABLED) {
    return;
}

// Get license status
$licenseValidator = new LicenseValidator();
$licenseStatus = $licenseValidator->validate();

// Determine banner style and message
if (!$licenseStatus['valid']) {
    $bannerClass = 'license-expired';
    $icon = '❌';
    $message = 'LICENSE EXPIRED';
    $details = $licenseStatus['message'] . ' - Contact support to renew your license.';
} elseif (isset($licenseStatus['is_trial']) && $licenseStatus['is_trial']) {
    $daysLeft = $licenseStatus['days_left'] ?? 0;
    if ($daysLeft <= 1) {
        $bannerClass = 'license-trial-urgent';
        $icon = '⚠️';
        $message = "TRIAL ENDING SOON - {$daysLeft} DAY" . ($daysLeft == 1 ? '' : 'S') . " LEFT";
        $details = 'Contact support to activate full license before trial expires.';
    } else {
        $bannerClass = 'license-trial';
        $icon = '⏰';
        $message = "TRIAL VERSION - {$daysLeft} DAYS REMAINING";
        $details = 'Enjoying the bot? Contact support to purchase a full license.';
    }
} else {
    // Paid license
    $daysLeft = $licenseStatus['days_left'] ?? 0;
    if ($daysLeft <= 7) {
        $bannerClass = 'license-expiring';
        $icon = '⚠️';
        $message = "LICENSE EXPIRING SOON - {$daysLeft} DAYS LEFT";
        $details = 'Contact support to renew your license.';
    } else {
        $bannerClass = 'license-active';
        $icon = '✅';
        $message = "LICENSE ACTIVE - {$daysLeft} DAYS REMAINING";
        $details = 'Thank you for your support!';
    }
}
?>

<style>
.license-banner {
    padding: 15px 20px;
    margin: 0 0 20px 0;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.license-banner-icon {
    font-size: 24px;
}

.license-banner-content {
    flex: 1;
}

.license-banner-message {
    font-size: 16px;
    margin-bottom: 4px;
}

.license-banner-details {
    font-size: 13px;
    font-weight: normal;
    opacity: 0.9;
}

.license-trial {
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: #333;
    border-left: 5px solid #f39c12;
}

.license-trial-urgent {
    background: linear-gradient(135deg, #ff9500 0%, #ffb84d 100%);
    color: #fff;
    border-left: 5px solid #e67e22;
    animation: pulse 2s infinite;
}

.license-expired {
    background: linear-gradient(135deg, #e74c3c 0%, #ff6b6b 100%);
    color: #fff;
    border-left: 5px solid #c0392b;
    animation: pulse 2s infinite;
}

.license-expiring {
    background: linear-gradient(135deg, #ff9500 0%, #ffb84d 100%);
    color: #fff;
    border-left: 5px solid #e67e22;
}

.license-active {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: #fff;
    border-left: 5px solid #229954;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}

.license-refresh-btn {
    background: rgba(255, 255, 255, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.5);
    color: inherit;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.license-refresh-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.license-refresh-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<div class="license-banner <?php echo $bannerClass; ?>">
    <div class="license-banner-icon"><?php echo $icon; ?></div>
    <div class="license-banner-content">
        <div class="license-banner-message"><?php echo htmlspecialchars($message); ?></div>
        <div class="license-banner-details"><?php echo htmlspecialchars($details); ?></div>
    </div>
    <button onclick="refreshLicense()" class="license-refresh-btn" title="Refresh license status from server">
        🔄 Refresh
    </button>
</div>

<script>
function refreshLicense() {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '⏳ Refreshing...';

    fetch('refresh-license.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ License refreshed successfully!\n\nReloading page...');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '🔄 Refresh';
            }
        })
        .catch(err => {
            alert('❌ Network error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '🔄 Refresh';
        });
}
</script>
