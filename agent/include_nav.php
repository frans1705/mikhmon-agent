<?php
/*
 * Agent Navigation Bar - Bottom Navigation for Mobile
 * Consistent with MikhMon styling
 */
if (!isset($_SESSION['agent_id'])) {
    return;
}

$agentName = $_SESSION['agent_name'] ?? 'Agent';
$agentCode = $_SESSION['agent_code'] ?? '';

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$currentPage = strtolower($currentPage);

// Map page names for active state detection
$pageMap = [
    'dashboard.php' => 'dashboard',
    'vouchers.php' => 'vouchers',
    'digital_products.php' => 'digiflazz',
    'transactions.php' => 'transactions',
    'billing_payments.php' => 'billing_payments',
    'logout.php' => 'logout'
];

$currentPageKey = $pageMap[$currentPage] ?? '';
$dashboardActive = ($currentPageKey === 'dashboard' || strpos($currentPage, 'dashboard') !== false);
$vouchersActive = ($currentPageKey === 'vouchers' || strpos($currentPage, 'voucher') !== false);
$digiflazzActive = ($currentPageKey === 'digiflazz' || (strpos($currentPage, 'digital') !== false && strpos($currentPage, 'transactions') === false));
$transactionsActive = ($currentPageKey === 'transactions' || strpos($currentPage, 'transaction') !== false);
$billingPaymentsActive = ($currentPageKey === 'billing_payments' || strpos($currentPage, 'billing_payments') !== false);
$logoutActive = ($currentPageKey === 'logout' || strpos($currentPage, 'logout') !== false);
?>
<div class="navbar-top">
    <div class="navbar-top-left">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fa fa-users"></i> MIKHMON Agent
        </a>
        <span class="navbar-text">
            <?= htmlspecialchars($agentName); ?> (<?= htmlspecialchars($agentCode); ?>)
        </span>
    </div>
    <div class="navbar-top-right">
        <a href="dashboard.php" class="navbar-menu-item <?= $dashboardActive ? 'active' : ''; ?>">
            <i class="fa fa-dashboard"></i> Dashboard
        </a>
        <a href="vouchers.php" class="navbar-menu-item <?= $vouchersActive ? 'active' : ''; ?>">
            <i class="fa fa-ticket"></i> Vouchers
        </a>
        <a href="digital_products.php" class="navbar-menu-item <?= $digiflazzActive ? 'active' : ''; ?>">
            <i class="fa fa-bolt"></i> Digital Products
        </a>
        <a href="billing_payments.php" class="navbar-menu-item <?= $billingPaymentsActive ? 'active' : ''; ?>">
            <i class="fa fa-money"></i> Billing Payments
        </a>
        <a href="transactions.php" class="navbar-menu-item <?= $transactionsActive ? 'active' : ''; ?>">
            <i class="fa fa-history"></i> Transactions
        </a>
        <a href="logout.php" class="navbar-menu-item <?= $logoutActive ? 'active' : ''; ?>">
            <i class="fa fa-sign-out"></i> Logout
        </a>
    </div>
</div>

<!-- Bottom Navigation -->
<?php
$dashboardActive = ($currentPageKey == 'dashboard' || strpos($currentPage, 'dashboard') !== false);
$vouchersActive = ($currentPageKey == 'vouchers' || strpos($currentPage, 'voucher') !== false);
$digiflazzActive = ($currentPageKey == 'digiflazz' || (strpos($currentPage, 'digital') !== false && strpos($currentPage, 'transactions') === false));
$transactionsActive = ($currentPageKey == 'transactions' || strpos($currentPage, 'transaction') !== false);
$billingPaymentsActive = ($currentPageKey == 'billing_payments' || strpos($currentPage, 'billing_payments') !== false);
$logoutActive = ($currentPageKey == 'logout' || strpos($currentPage, 'logout') !== false);
?>
<nav class="bottom-nav" id="agentBottomNav" style="position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; max-width: 100vw !important; background-color: #3a4149 !important; display: flex !important; justify-content: space-around !important; align-items: center !important; padding: 8px 0 !important; z-index: 1000 !important; flex-wrap: nowrap !important; overflow: hidden !important; box-sizing: border-box !important;">
    <a href="dashboard.php" class="nav-item <?= $dashboardActive ? 'active' : ''; ?>" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 20% !important; visibility: visible !important; opacity: 1 !important; text-decoration: none !important; color: <?= $dashboardActive ? '#20a8d8' : '#999'; ?> !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;">
        <i class="fa fa-dashboard" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 20px !important; margin-bottom: 2px !important;"></i>
        <span class="nav-label" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 10px !important; line-height: 1.2 !important; white-space: nowrap !important;">Dashboard</span>
    </a>
    <a href="vouchers.php" class="nav-item <?= $vouchersActive ? 'active' : ''; ?>" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 20% !important; visibility: visible !important; opacity: 1 !important; text-decoration: none !important; color: <?= $vouchersActive ? '#20a8d8' : '#999'; ?> !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;">
        <i class="fa fa-ticket" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 20px !important; margin-bottom: 2px !important;"></i>
        <span class="nav-label" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 10px !important; line-height: 1.2 !important; white-space: nowrap !important;">Vouchers</span>
    </a>
    <a href="digital_products.php" class="nav-item <?= $digiflazzActive ? 'active' : ''; ?>" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 20% !important; visibility: visible !important; opacity: 1 !important; text-decoration: none !important; color: <?= $digiflazzActive ? '#20a8d8' : '#999'; ?> !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;">
        <i class="fa fa-bolt" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 20px !important; margin-bottom: 2px !important;"></i>
        <span class="nav-label" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 10px !important; line-height: 1.2 !important; white-space: nowrap !important;">Digital</span>
    </a>
    <a href="billing_payments.php" class="nav-item <?= $billingPaymentsActive ? 'active' : ''; ?>" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 20% !important; visibility: visible !important; opacity: 1 !important; text-decoration: none !important; color: <?= $billingPaymentsActive ? '#20a8d8' : '#999'; ?> !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;">
        <i class="fa fa-money" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 20px !important; margin-bottom: 2px !important;"></i>
        <span class="nav-label" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 10px !important; line-height: 1.2 !important; white-space: nowrap !important;">Billing</span>
    </a>
    <a href="transactions.php" class="nav-item <?= $transactionsActive ? 'active' : ''; ?>" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 20% !important; visibility: visible !important; opacity: 1 !important; text-decoration: none !important; color: <?= $transactionsActive ? '#20a8d8' : '#999'; ?> !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;">
        <i class="fa fa-history" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 20px !important; margin-bottom: 2px !important;"></i>
        <span class="nav-label" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 10px !important; line-height: 1.2 !important; white-space: nowrap !important;">Transactions</span>
    </a>
    <a href="logout.php" class="nav-item <?= $logoutActive ? 'active' : ''; ?>" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 20% !important; visibility: visible !important; opacity: 1 !important; text-decoration: none !important; color: <?= $logoutActive ? '#20a8d8' : '#999'; ?> !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;">
        <i class="fa fa-sign-out" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 20px !important; margin-bottom: 2px !important;"></i>
        <span class="nav-label" style="display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 10px !important; line-height: 1.2 !important; white-space: nowrap !important;">Logout</span>
    </a>
</nav>

<style>
/* Top navbar - minimal, only shows on desktop */
.navbar-top {
    background-color: #3a4149;
    border-bottom: 1px solid #23282c;
    color: #e4e7ea;
    padding: 0;
    position: fixed;
    top: 0;
    width: 100%;
    height: 50px;
    z-index: 9;
    display: flex;
    align-items: center;
    padding: 0 20px;
}

.navbar-top-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.navbar-top-right {
    display: flex;
    align-items: center;
    gap: 5px;
}

.navbar-brand {
    color: #e4e7ea;
    text-decoration: none;
    font-weight: bold;
    font-size: 17px;
    transition: .3s;
}

.navbar-brand:hover {
    color: #fff;
}

.navbar-text {
    color: #999;
    font-size: 13px;
}

.navbar-menu-item {
    color: #999;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.navbar-menu-item i {
    font-size: 14px;
}

.navbar-menu-item:hover {
    color: #e4e7ea;
    background-color: rgba(255,255,255,0.05);
}

.navbar-menu-item.active {
    color: #20a8d8;
    background-color: rgba(32,168,216,0.1);
}

.navbar-menu-item.active:hover {
    color: #20a8d8;
    background-color: rgba(32,168,216,0.15);
}

/* Bottom Navigation - Mobile First */
.bottom-nav {
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    background-color: #3a4149 !important;
    border-top: 1px solid #23282c !important;
    display: flex !important;
    justify-content: space-around !important;
    align-items: center !important;
    padding: 8px 0 !important;
    z-index: 1000 !important;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1) !important;
    margin: 0 !important;
    overflow: visible !important;
    flex-wrap: nowrap !important;
}

/* Ensure all nav items are always visible */
.bottom-nav > .nav-item {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
    width: auto !important;
    flex: 1 1 25% !important;
    min-width: 0 !important;
    max-width: 25% !important;
}

.nav-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    color: #999 !important;
    padding: 8px 4px !important;
    border-radius: 8px !important;
    transition: all 0.3s !important;
    min-width: 0 !important;
    flex: 1 1 0 !important;
    max-width: none !important;
    visibility: visible !important;
    opacity: 1 !important;
    overflow: visible !important;
}

.nav-item i {
    font-size: 20px !important;
    margin-bottom: 2px !important;
    transition: all 0.3s !important;
    display: block !important;
    visibility: visible !important;
}

.nav-item .nav-label {
    font-size: 10px !important;
    font-weight: 500 !important;
    transition: all 0.3s !important;
    display: block !important;
    visibility: visible !important;
    white-space: nowrap !important;
    overflow: visible !important;
    text-overflow: ellipsis !important;
}

.nav-item:hover {
    color: #e4e7ea;
    background-color: rgba(255,255,255,0.05);
}

.nav-item.active {
    color: #20a8d8 !important;
    background-color: rgba(32,168,216,0.1) !important;
}

.nav-item.active i {
    color: #20a8d8 !important;
    transform: scale(1.1);
}

.nav-item.active .nav-label {
    color: #20a8d8 !important;
    font-weight: 600;
}

/* Content wrapper - adjust for top navbar and bottom nav */
.content-wrapper {
    margin-top: 50px;
    padding: 10px;
    padding-bottom: 10px; 
    min-height: calc(100vh - 60px);
}

/* Desktop - show top navbar with menu, hide bottom nav */
@media (min-width: 769px) {
    .navbar-top {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    }
    
    .navbar-top-left {
        display: flex !important;
    }
    
    .navbar-top-right {
        display: flex !important;
    }
    
    .navbar-text {
        display: inline !important;
    }
    
    .navbar-menu-item {
        display: flex !important;
    }
    
    .bottom-nav {
        display: none !important;
    }
    
    .content-wrapper {
        padding-bottom: 10px !important;
    }
}

/* Mobile - show bottom nav, hide top navbar text and menu */
@media (max-width: 768px) {
    .navbar-top {
        display: flex !important;
        justify-content: center;
    }
    
    .navbar-top-right {
        display: none !important;
    }
    
    .navbar-text {
        display: none !important;
    }
    
    .bottom-nav {
        display: flex !important;
        flex-wrap: nowrap !important;
        overflow: hidden !important;
        padding: 6px 0 !important;
        width: 100vw !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    
    .bottom-nav .nav-item {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        max-width: 25% !important;
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        height: auto !important;
        width: auto !important;
        padding: 4px 2px !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    
    .bottom-nav .nav-item i {
        font-size: 18px !important;
        margin-bottom: 2px !important;
    }
    
    .bottom-nav .nav-item .nav-label {
        font-size: 9px !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
    }
    
    .bottom-nav .nav-item:nth-child(1),
    .bottom-nav .nav-item:nth-child(2),
    .bottom-nav .nav-item:nth-child(3),
    .bottom-nav .nav-item:nth-child(4) {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        flex: 1 1 0 !important;
    }
    
    .content-wrapper {
        padding-bottom: 70px !important;
        overflow-x: visible !important;
    }
    
    /* Prevent horizontal scroll on body, but allow table scroll */
    body {
        overflow-x: hidden !important;
        max-width: 100vw !important;
    }
    
    html {
        overflow-x: hidden !important;
        max-width: 100vw !important;
    }
    
    /* Allow table containers to scroll horizontally */
    .table-responsive {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    
    .card-body {
        overflow-x: visible !important;
    }
    
    /* Ensure bottom nav doesn't cover content */
    .row {
        margin-bottom: 10px;
    }
    
    /* Ensure body has enough space for bottom nav */
    body {
        padding-bottom: 0 !important;
    }
    
    html {
        padding-bottom: 0 !important;
    }
}

/* Very small screens - adjust nav item size */
@media (max-width: 360px) {
    .nav-item {
        padding: 6px 8px;
        min-width: 50px;
    }
    
    .nav-item i {
        font-size: 20px;
    }
    
    .nav-item .nav-label {
        font-size: 10px;
    }
}
</style>

<script>
// Ensure bottom nav is always visible on mobile and consistent across all pages
(function() {
    'use strict';
    
    function initBottomNav() {
        const bottomNav = document.getElementById('agentBottomNav');
        const contentWrapper = document.querySelector('.content-wrapper');
        
        if (!bottomNav) {
            console.warn('Bottom nav not found');
            return;
        }
        
        if (!contentWrapper) {
            console.warn('Content wrapper not found');
            return;
        }
        
        // Get all nav items
        const navItems = bottomNav.querySelectorAll('.nav-item');
        
        function ensureBottomNavVisible() {
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                // Force bottom nav to be visible on mobile
                bottomNav.style.cssText = 'position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; display: flex !important; z-index: 1000 !important; flex-wrap: nowrap !important; overflow: visible !important;';
                
                // Ensure nav items are visible and fit within screen
                navItems.forEach(function(item, index) {
                    item.style.cssText = 'display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; min-width: 0 !important; max-width: 25% !important; visibility: visible !important; opacity: 1 !important; overflow: hidden !important; height: auto !important; width: auto !important; padding: 4px 2px !important; margin: 0 !important; box-sizing: border-box !important;';
                    
                    // Ensure icon is visible
                    const icon = item.querySelector('i');
                    if (icon) {
                        icon.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 18px !important; margin-bottom: 2px !important;';
                    }
                    
                    // Ensure label is visible
                    const label = item.querySelector('.nav-label');
                    if (label) {
                        label.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; font-size: 9px !important; line-height: 1.2 !important; white-space: nowrap !important;';
                    }
                });
                
                bottomNav.style.cssText = 'position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; width: 100vw !important; max-width: 100% !important; background-color: #3a4149 !important; display: flex !important; justify-content: space-around !important; align-items: center !important; padding: 6px 0 !important; z-index: 1000 !important; flex-wrap: nowrap !important; overflow: hidden !important; box-sizing: border-box !important;';
                
                // Ensure content has padding for bottom nav
                const currentPadding = window.getComputedStyle(contentWrapper).paddingBottom;
                if (parseInt(currentPadding) < 70) {
                    contentWrapper.style.paddingBottom = '80px';
                }
            } else {
                // Hide on desktop
                bottomNav.style.display = 'none';
                contentWrapper.style.paddingBottom = '10px';
            }
        }
        
        // Initial check
        ensureBottomNavVisible();
        
        // Check on resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(ensureBottomNavVisible, 100);
        });
        
        // Check on orientation change (mobile)
        window.addEventListener('orientationchange', function() {
            setTimeout(ensureBottomNavVisible, 200);
        });
        
        // Force check after page load
        setTimeout(ensureBottomNavVisible, 500);
        
        // Force check again after a short delay to ensure everything is rendered
        setTimeout(ensureBottomNavVisible, 1000);
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBottomNav);
    } else {
        initBottomNav();
    }
})();
</script>
