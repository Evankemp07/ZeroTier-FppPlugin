<?php
// ZeroTier Network Management Page (Combined Status & Configuration)

// Use $plugin if set by FPP, otherwise use default
$pluginName = isset($plugin) ? $plugin : "ZeroTier-FppPlugin";

// Handle FPP plugin context - check if $settings exists
if (!isset($settings) || !isset($settings['pluginDirectory'])) {
    // Fallback for direct access or local dev
    global $settings;
    if (!isset($settings)) {
        $settings = array();
    }
    if (!isset($settings['pluginDirectory'])) {
        $settings['pluginDirectory'] = dirname(__FILE__);
    }
}

$pluginPath = $settings['pluginDirectory'] . "/" . $pluginName;
// Use relative path for CSS - works in both local dev and production
$cssPath = "styles.css";
?>

<style>
<?php
// Embed CSS directly
$cssFile = $pluginPath . '/styles.css';
if (file_exists($cssFile)) {
    readfile($cssFile);
} else {
    // Fallback: try relative path
    $cssFile = dirname(__FILE__) . '/styles.css';
    if (file_exists($cssFile)) {
        readfile($cssFile);
    }
}
?>
    .zt-container {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        position: relative;
    }
    
    .zt-card {
        width: 100% !important;
        margin: 0 !important;
        border-radius: 12px !important;
    }
    
    .zt-card-body {
        padding: 24px !important;
    }
    
    .zt-form-input {
        height: auto !important;
        min-height: 44px !important;
        box-sizing: border-box !important;
        background: #f5f5f7 !important;
        border-radius: 12px !important;
    }
    
    .zt-btn {
        height: 36px !important;
        box-sizing: border-box !important;
        padding: 8px 16px !important;
        font-size: 14px !important;
        border-radius: 10px !important;
    }
    
    @media (prefers-color-scheme: dark) {
        .zt-form-input {
            background: #2c2c2e !important;
        }
    }
    
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    #pageWrapper, #pageContent {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .zt-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding: 20px 24px;
        background: #f5f5f7;
        border-radius: 12px 12px 0 0;
        margin-left: -24px;
        margin-right: -24px;
        margin-top: -24px;
    }
    
    .zt-page-title {
        margin-bottom: 0;
        margin-top: 0;
    }
    
    @media (prefers-color-scheme: dark) {
        .zt-card-header {
            background: #1d1d1f;
        }
    }
    
    .zt-header-btn {
        display: inline-block;
        padding: 8px 16px;
        background-color: #007AFF;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }
    
    .zt-header-btn:hover {
        background-color: #0051D5;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
    }
    
    .zt-header-btn:active {
        transform: translateY(0);
    }
    
    @media (prefers-color-scheme: dark) {
        html, body {
            background-color: #000000 !important;
            color: #f5f5f7 !important;
        }
        body, body > *, #pageWrapper, #pageContent {
            background-color: #000000 !important;
        }
        .zt-container {
            background-color: transparent !important;
        }
        
        
        .zt-form-input {
            background: #2c2c2e !important;
            border-color: #d2d2d7 !important;
            color: #f5f5f7 !important;
        }
        
        .zt-form-input:focus {
            border-color: #007AFF !important;
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1) !important;
        }
        
        .zt-form-input::placeholder {
            color: #86868b !important;
        }
        
        .zt-form-label {
            color: #f5f5f7 !important;
        }
        
        #debug-container {
            background: #1d1d1f !important;
            color: #f5f5f7 !important;
        }
        #debug-container h4 {
            color: #f5f5f7 !important;
        }
        #debug-container h4:hover {
            opacity: 0.8;
        }
        #debug-container #debug-messages div {
            background: #2c2c2e !important;
            color: #f5f5f7 !important;
        }
        #debug-container pre {
            background: #2c2c2e !important;
            color: #f5f5f7 !important;
        }
        .zt-header-btn {
            background-color: #007AFF !important;
            color: white !important;
        }
        .zt-header-btn:hover {
            background-color: #0051D5 !important;
        }
        .zt-card-header {
            background: #1d1d1f !important;
        }
    }
    
    /* Mobile table scrolling */
    #networks-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-left: -24px;
        margin-right: -24px;
        padding-left: 24px;
        padding-right: 24px;
    }
    
    #networks-container .zt-table {
        min-width: 700px;
        width: 100%;
    }
    
    @media (max-width: 768px) {
        #networks-container {
            margin-left: -24px;
            margin-right: -24px;
            padding-left: 24px;
            padding-right: 24px;
        }
        
        #networks-container .zt-table {
            min-width: 800px;
        }
    }
</style>

<div class="zt-container">
    <div class="zt-card">
        <div class="zt-card-body">
            <div class="zt-card-header">
                <h2 class="zt-page-title">ZeroTier Network Manager</h2>
                <a href="https://accounts.zerotier.com/realms/zerotier/protocol/openid-connect/auth?client_id=central-v2" target="_blank" class="zt-header-btn">
                    ZeroTier Central
                </a>
            </div>
            <div id="message-container"></div>
            
            <div id="zerotier-status-check">
                <p>Checking ZeroTier status...</p>
            </div>
            
            <div id="main-content" style="display: none;">
                <!-- Node Information Section -->
                <h3 class="zt-section-title">Node Information</h3>
                <div id="node-info-container">
                    <p>Loading node information...</p>
                </div>
                
                <!-- Join Network Section -->
                <h3 class="zt-section-title">Join Network</h3>
                <form id="joinForm">
                    <div class="zt-form-group">
                        <label class="zt-form-label" for="join_network_id">Network ID</label>
                        <div class="zt-form-row" style="position: relative; display: inline-block; width: 100%; max-width: 420px;">
                            <input type="text" 
                                   id="join_network_id" 
                                   name="network_id" 
                                   class="zt-form-input"
                                   placeholder="Enter network ID to join!"
                                   required
                                   style="padding-right: 120px;">
                            <button type="submit" class="zt-btn zt-btn-primary" id="joinBtn" style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); margin: 0;">Join Network</button>
                        </div>
                        <p class="zt-note">
                            After joining, you may need to authorize this node in the ZeroTier web interface.
                        </p>
                    </div>
                </form>
                
                <!-- Networks List Section -->
                <h3 class="zt-section-title">Joined Networks</h3>
                <div id="networks-container">
                    <p>Loading networks...</p>
                </div>
                
                <!-- Debug Section -->
                <div id="debug-container" style="margin-top: 40px; margin-bottom: 0; padding: 12px 20px; background: #f5f5f7; border-radius: 12px; font-family: 'SF Mono', 'Monaco', 'Menlo', monospace; font-size: 12px;">
                    <h4 style="margin: 0; color: #1d1d1f; font-weight: 600; cursor: pointer; user-select: none; display: flex; align-items: center; gap: 8px;" onclick="toggleDebug()">
                        <span id="debug-toggle-icon" style="display: inline-block; transition: transform 0.2s; transform: rotate(-90deg);">▼</span>
                        Debug Messages
                    </h4>
                    <div id="debug-messages" class="zt-debug-hidden" style="color: #666; line-height: 1.6; max-height: 300px; overflow-y: auto; display: none; margin-top: 12px;">
                        <div>No debug messages yet...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const API_BASE = '/api/plugin/ZeroTier-FppPlugin';
    
    // Toggle debug section
    window.toggleDebug = function() {
        const debugMessages = document.getElementById('debug-messages');
        const toggleIcon = document.getElementById('debug-toggle-icon');
        if (!debugMessages || !toggleIcon) return;
        
        const isHidden = debugMessages.style.display === 'none' || 
                        (debugMessages.style.display === '' && debugMessages.classList.contains('zt-debug-hidden'));
        
        if (isHidden) {
            debugMessages.style.display = 'block';
            debugMessages.classList.remove('zt-debug-hidden');
            toggleIcon.style.transform = 'rotate(0deg)';
        } else {
            debugMessages.style.display = 'none';
            debugMessages.classList.add('zt-debug-hidden');
            toggleIcon.style.transform = 'rotate(-90deg)';
        }
    };
    
    // Debug logging function
    function debugLog(message, data = null) {
        const debugContainer = document.getElementById('debug-messages');
        if (!debugContainer) return;
        
        // Remove "No debug messages yet..." placeholder if it exists
        const placeholder = debugContainer.querySelector('div:only-child');
        if (placeholder && placeholder.textContent === 'No debug messages yet...') {
            placeholder.remove();
        }
        
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.style.marginBottom = '8px';
        logEntry.style.padding = '8px';
        logEntry.style.background = '#fff';
        logEntry.style.borderRadius = '6px';
        logEntry.style.borderLeft = '3px solid #007AFF';
        
        let content = '<strong style="color: #007AFF;">[' + timestamp + ']</strong> ' + escapeHtml(message);
        if (data) {
            content += '<pre style="margin: 8px 0 0 0; padding: 8px; background: #f9f9f9; border-radius: 4px; overflow-x: auto; font-size: 11px;">' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
        }
        logEntry.innerHTML = content;
        
        debugContainer.insertBefore(logEntry, debugContainer.firstChild);
        
        // Keep only last 15 messages
        while (debugContainer.children.length > 15) {
            debugContainer.removeChild(debugContainer.lastChild);
        }
    }
    
        // Utility function to show messages
        function showMessage(message, type) {
            const container = document.getElementById('message-container');
            const alertClass = 'zt-alert zt-alert-' + type;
            const msgDiv = document.createElement('div');
            msgDiv.className = alertClass;
            msgDiv.innerHTML = escapeHtml(message);
            // Start hidden, then animate in
            msgDiv.style.opacity = '0';
            msgDiv.style.transform = 'translateY(-20px)';
            container.innerHTML = '';
            container.appendChild(msgDiv);
            // Trigger animation
            setTimeout(() => {
                msgDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                msgDiv.style.opacity = '1';
                msgDiv.style.transform = 'translateY(0)';
            }, 10);

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    if (msgDiv.parentNode) {
                        msgDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease, margin-top 0.5s ease';
                        msgDiv.style.opacity = '0';
                        msgDiv.style.transform = 'translateY(-20px)';
                        msgDiv.style.marginTop = '0';
                        msgDiv.style.marginBottom = '0';
                        setTimeout(function() {
                            if (msgDiv.parentNode) {
                                msgDiv.remove();
                            }
                        }, 500);
                    }
                }, 5000);
            }
        }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Check for ZeroTier updates
    function checkForUpdates(currentVersion) {
        debugLog('Checking for updates...', { currentVersion: currentVersion });
        fetch(API_BASE + '/check-update')
            .then(response => response.json())
            .then(data => {
                debugLog('Update check response', data);
                if (data.success && data.updateAvailable) {
                    const messageContainer = document.getElementById('message-container');
                    const updateHint = document.createElement('div');
                    updateHint.id = 'update-hint';
                    updateHint.className = 'zt-alert zt-alert-warning';
                    updateHint.style.marginBottom = '24px';
                    updateHint.innerHTML = 
                        '<strong>Update Available!</strong> ZeroTier ' + escapeHtml(data.latestVersion) + 
                        ' is available (you have ' + escapeHtml(currentVersion) + '). ' +
                        '<button onclick="performUpdate()" id="update-btn" class="zt-btn zt-btn-primary" style="margin-left: 12px; padding: 6px 16px; font-size: 13px;">Update now</button>';
                    // Start hidden, then animate in
                    updateHint.style.opacity = '0';
                    updateHint.style.transform = 'translateY(-20px)';
                    messageContainer.insertBefore(updateHint, messageContainer.firstChild);
                    // Trigger animation
                    setTimeout(() => {
                        updateHint.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        updateHint.style.opacity = '1';
                        updateHint.style.transform = 'translateY(0)';
                    }, 10);
                }
            })
            .catch(error => {
                // Silently fail - update check is not critical
                console.log('Update check failed:', error);
            });
    }
    
    // Perform ZeroTier update
    window.performUpdate = function() {
        debugLog('Starting ZeroTier update...');
        const updateBtn = document.getElementById('update-btn');
        const updateHint = document.getElementById('update-hint');
        
        if (!updateBtn || updateBtn.disabled) return;
        
        // Show loading state
        updateBtn.disabled = true;
        updateBtn.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 8px;">' +
            '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">' +
            '<circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-dasharray="31.416" stroke-dashoffset="23.562" stroke-linecap="round"/>' +
            '</svg> Updating...</span>';
        
        fetch(API_BASE + '/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            debugLog('Update response', data);
            if (data.success) {
                // Show success message with slide-in animation
                updateHint.className = 'zt-alert zt-alert-success';
                updateHint.innerHTML = '<strong>Update finished!</strong> ZeroTier has been updated successfully.';
                updateHint.style.opacity = '0';
                updateHint.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    updateHint.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    updateHint.style.opacity = '1';
                    updateHint.style.transform = 'translateY(0)';
                }, 10);
                
                // Fade out after 10 seconds
                setTimeout(() => {
                    updateHint.style.transition = 'opacity 0.5s ease, transform 0.5s ease, margin-top 0.5s ease';
                    updateHint.style.opacity = '0';
                    updateHint.style.transform = 'translateY(-20px)';
                    updateHint.style.marginTop = '0';
                    updateHint.style.marginBottom = '0';
                    setTimeout(() => {
                        updateHint.remove();
                    }, 500);
                }, 10000);
            } else {
                // Show error
                updateHint.className = 'zt-alert zt-alert-error';
                updateHint.innerHTML = '<strong>Update failed:</strong> ' + escapeHtml(data.error || 'Unknown error');
                updateBtn.disabled = false;
                updateBtn.innerHTML = 'Update now';
            }
        })
        .catch(error => {
            updateHint.className = 'zt-alert zt-alert-error';
            updateHint.innerHTML = '<strong>Update failed:</strong> ' + escapeHtml(error.message);
            updateBtn.disabled = false;
            updateBtn.innerHTML = 'Update now';
        });
    };
    
    // Copy Node ID to clipboard
    window.copyNodeId = function(nodeId, evt) {
        if (!nodeId) return;
        
        const btn = evt ? evt.currentTarget : null;
        
        navigator.clipboard.writeText(nodeId).then(function() {
            // Show feedback
            if (btn) {
                const originalHTML = btn.innerHTML;
                const isSmall = btn.querySelector('svg[width="14"]');
                const checkmarkSize = isSmall ? '14' : '16';
                btn.innerHTML = '<svg width="' + checkmarkSize + '" height="' + checkmarkSize + '" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 8L6 11L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                btn.style.opacity = '1';
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.style.opacity = '0.6';
                }, 2000);
            }
        }).catch(function(err) {
            console.error('Failed to copy:', err);
        });
    };
    
    // Load node info
    function loadNodeInfo() {
        debugLog('Loading node info...');
        fetch(API_BASE + '/info')
            .then(response => response.json())
            .then(data => {
                debugLog('Node info response', data);
                const statusCheck = document.getElementById('zerotier-status-check');
                const mainContent = document.getElementById('main-content');
                
                if (!data.success) {
                    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                    const errorMsg = data.error ? escapeHtml(data.error) : 'Please ensure ZeroTier is installed and running.';
                    let instructions = '';
                    
                    if (isMac) {
                        instructions = '<br><br><strong>On macOS:</strong><br>' +
                            '• Open the ZeroTier app from Applications, or<br>' +
                            '• Run: <span class="zt-code">open -a ZeroTier</span><br>' +
                            '• Or install via Homebrew: <span class="zt-code">brew install zerotier-one</span>';
                    } else {
                        instructions = '<br><br>Try running: <span class="zt-code">sudo systemctl start zerotier-one</span>';
                    }
                    
                    statusCheck.innerHTML = 
                        '<div class="zt-alert zt-alert-error">' +
                        '<strong>ZeroTier is not installed or not accessible.</strong><br>' +
                        errorMsg +
                        instructions +
                        '</div>';
                    return;
                }
                
                // Show main content
                statusCheck.style.display = 'none';
                mainContent.style.display = 'block';
                
                // Update node info display
                const nodeInfoContainer = document.getElementById('node-info-container');
                const statusClass = (data.status === 'ONLINE') ? 'zt-status-online' : 'zt-status-offline';
                
                let infoHtml = '<div class="zt-info-grid">' +
                    '<div class="zt-info-item">' +
                    '<div class="zt-info-label">Node ID</div>' +
                    '<div class="zt-info-value" style="display: flex; align-items: center; gap: 8px;">' +
                    '<span>' + escapeHtml(data.nodeId || 'Unknown') + '</span>' +
                    '<button onclick="copyNodeId(\'' + escapeHtml(data.nodeId || '') + '\', event)" ' +
                    'class="zt-copy-btn" title="Copy Node ID" style="background: none; border: none; cursor: pointer; padding: 4px; display: inline-flex; align-items: center; justify-content: center; opacity: 0.6; transition: opacity 0.2s; color: #007AFF;" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0.6\'">' +
                    '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                    '<path d="M5.5 2.5H11.5C12.0523 2.5 12.5 2.94772 12.5 3.5V9.5C12.5 10.0523 12.0523 10.5 11.5 10.5H9.5V12.5C9.5 13.0523 9.05228 13.5 8.5 13.5H2.5C1.94772 13.5 1.5 13.0523 1.5 12.5V6.5C1.5 5.94772 1.94772 5.5 2.5 5.5H4.5V3.5C4.5 2.94772 4.94772 2.5 5.5 2.5Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '<path d="M4.5 5.5H8.5C9.05228 5.5 9.5 5.94772 9.5 6.5V10.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '</svg>' +
                    '</button>' +
                    '</div>' +
                    '</div>' +
                    '<div class="zt-info-item">' +
                    '<div class="zt-info-label">Version</div>' +
                    '<div class="zt-info-value">' + escapeHtml(data.version || 'Unknown') + '</div>' +
                    '</div>' +
                    '<div class="zt-info-item ' + statusClass + '-full">' +
                    '<div class="zt-info-label">Status</div>' +
                    '<div class="zt-info-value">' +
                    escapeHtml(data.status || 'Unknown') +
                    '</div>' +
                    '</div>';
                
                infoHtml += '</div>';
                
                nodeInfoContainer.innerHTML = infoHtml;
                
                // Check for updates
                checkForUpdates(data.version);
            })
            .catch(error => {
                console.error('Error loading node info:', error);
                document.getElementById('zerotier-status-check').innerHTML = 
                    '<div class="zt-alert zt-alert-error">' +
                    '<strong>Error:</strong> Could not connect to API. ' + escapeHtml(error.message) +
                    '</div>';
            });
    }
    
    // Load networks list
    function loadNetworks() {
        debugLog('Loading networks list...');
        fetch(API_BASE + '/networks')
            .then(response => response.json())
            .then(data => {
                debugLog('Networks list response', data);
                const container = document.getElementById('networks-container');
                
                if (!data.success) {
                    container.innerHTML = 
                        '<div class="zt-alert zt-alert-error">' +
                        '<strong>Could not retrieve network list.</strong><br>' +
                        (data.error ? escapeHtml(data.error) : 'Unknown error') +
                        '<br><br>Try running from command line: <span class="zt-code">zerotier-cli listnetworks</span> or <span class="zt-code">shownet info</span></div>';
                    return;
                }
                
                if (!data.networks || data.networks.length === 0) {
                    container.innerHTML = 
                        '<div class="zt-alert zt-alert-warning">' +
                        'Not currently joined to any networks. Use the form above to join a network.' +
                        '</div>';
                    return;
                }
                
                let html = '<table class="zt-table">' +
                    '<thead>' +
                    '<tr>' +
                    '<th>Network ID</th>' +
                    '<th>Name</th>' +
                    '<th>Status</th>' +
                    '<th>Type</th>' +
                    '<th>Interface</th>' +
                    '<th>IP Address</th>' +
                    '<th>Actions</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';
                
                data.networks.forEach(function(network) {
                    const statusClass = network.status === 'OK' ? 'zt-status-ok' : 'zt-status-pending';
                    const ipDisplay = (network.ip && network.ip !== '-') ? escapeHtml(network.ip) : '<span class="zt-ip-not-assigned">Not assigned</span>';
                    
                    html += '<tr>' +
                        '<td class="zt-table-cell-monospace">' +
                        '<div style="display: inline-flex; align-items: center; gap: 6px;">' +
                        '<span>' + escapeHtml(network.networkId) + '</span>' +
                        '<button onclick="copyNodeId(\'' + escapeHtml(network.networkId) + '\', event)" ' +
                        'class="zt-copy-btn" title="Copy Network ID" style="background: none; border: none; cursor: pointer; padding: 2px; display: inline-flex; align-items: center; justify-content: center; opacity: 0.6; transition: opacity 0.2s; color: #007AFF;" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0.6\'">' +
                        '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                        '<path d="M5.5 2.5H11.5C12.0523 2.5 12.5 2.94772 12.5 3.5V9.5C12.5 10.0523 12.0523 10.5 11.5 10.5H9.5V12.5C9.5 13.0523 9.05228 13.5 8.5 13.5H2.5C1.94772 13.5 1.5 13.0523 1.5 12.5V6.5C1.5 5.94772 1.94772 5.5 2.5 5.5H4.5V3.5C4.5 2.94772 4.94772 2.5 5.5 2.5Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '<path d="M4.5 5.5H8.5C9.05228 5.5 9.5 5.94772 9.5 6.5V10.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '</svg>' +
                        '</button>' +
                        '</div>' +
                        '</td>' +
                        '<td>' + escapeHtml(network.name || '-') + '</td>' +
                        '<td><span class="zt-status-badge ' + statusClass + '">' + escapeHtml(network.status) + '</span></td>' +
                        '<td>' + escapeHtml(network.type || '-') + '</td>' +
                        '<td class="zt-table-cell-monospace">' + escapeHtml(network.interface || '-') + '</td>' +
                        '<td class="zt-table-cell-monospace">' + ipDisplay + '</td>' +
                        '<td>' +
                        '<button onclick="leaveNetwork(\'' + escapeHtml(network.networkId) + '\')" ' +
                        'class="zt-btn zt-btn-danger">Leave</button>' +
                        '</td>' +
                        '</tr>';
                });
                
                html += '</tbody></table>';
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading networks:', error);
                document.getElementById('networks-container').innerHTML = 
                    '<div class="zt-alert zt-alert-error">' +
                    '<strong>Error:</strong> Could not load networks. ' + escapeHtml(error.message) +
                    '</div>';
            });
    }
    
    // Join network
    function joinNetwork(networkId) {
        debugLog('Attempting to join network', { networkId: networkId, length: networkId.length });
        
        const btn = document.getElementById('joinBtn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Joining...';
        
        fetch(API_BASE + '/join', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ networkId: networkId })
        })
        .then(response => response.json())
        .then(data => {
            debugLog('Join network response', data);
            btn.disabled = false;
            btn.textContent = originalText;
            
            if (data.success) {
                showMessage('Successfully joined network: ' + networkId, 'success');
                document.getElementById('join_network_id').value = '';
                // Reload networks list after a short delay
                setTimeout(loadNetworks, 1000);
            } else {
                const errorMsg = data.error || (data.output ? data.output.join(' ') : 'Unknown error');
                showMessage('Failed to join network: ' + errorMsg, 'error');
            }
        })
        .catch(error => {
            debugLog('Join network error', { error: error.message, stack: error.stack });
            btn.disabled = false;
            btn.textContent = originalText;
            showMessage('Error joining network: ' + error.message, 'error');
        });
    }
    
    // Leave network (global function so onclick can access it)
    window.leaveNetwork = function(networkId) {
        if (!confirm('Are you sure you want to leave this network?')) {
            return;
        }
        
        debugLog('Attempting to leave network', { networkId: networkId });
        
        fetch(API_BASE + '/leave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ networkId: networkId })
        })
        .then(response => response.json())
        .then(data => {
            debugLog('Leave network response', data);
            if (data.success) {
                showMessage('Successfully left network: ' + networkId, 'success');
                // Reload networks list after a short delay (give ZeroTier time to update)
                setTimeout(loadNetworks, 1500);
                // Also reload node info to refresh the networks there
                setTimeout(loadNodeInfo, 1500);
            } else {
                const errorMsg = data.error || (data.output ? data.output.join(' ') : 'Unknown error');
                showMessage('Failed to leave network: ' + errorMsg, 'error');
            }
        })
        .catch(error => {
            showMessage('Error leaving network: ' + error.message, 'error');
        });
    };
    
    // Handle join form submission
    document.addEventListener('DOMContentLoaded', function() {
        const joinForm = document.getElementById('joinForm');
        const networkIdInput = document.getElementById('join_network_id');
        
        if (joinForm && networkIdInput) {
            // Validate input as user types
            let errorTimeout, successTimeout;
            
            function validateInput() {
                const input = networkIdInput;
                const networkId = input.value.trim();
                const cleanNetworkId = networkId.replace(/[^0-9a-fA-F]/g, '');
                
                // Clear any existing timeouts
                if (errorTimeout) clearTimeout(errorTimeout);
                if (successTimeout) clearTimeout(successTimeout);
                
                // Remove previous classes
                input.classList.remove('zt-input-error', 'zt-input-success');
                
                if (networkId.length === 0) {
                    return; // Don't show feedback for empty input
                }
                
                // Show feedback immediately
                if (cleanNetworkId.length === 16 && /^[0-9a-fA-F]{16}$/i.test(cleanNetworkId)) {
                    // Valid format - show green
                    input.classList.add('zt-input-success');
                    successTimeout = setTimeout(() => {
                        input.classList.remove('zt-input-success');
                    }, 2000);
                } else {
                    // Invalid format - show red
                    input.classList.add('zt-input-error');
                    errorTimeout = setTimeout(() => {
                        input.classList.remove('zt-input-error');
                    }, 2000);
                }
            }
            
            networkIdInput.addEventListener('input', validateInput);
            networkIdInput.addEventListener('paste', function() {
                setTimeout(validateInput, 10);
            });
            
            joinForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const networkId = networkIdInput.value.trim();
                // Clean network ID (remove spaces/dashes and any non-hex characters)
                const cleanNetworkId = networkId.replace(/[^0-9a-fA-F]/g, '');
                
                debugLog('Form submitted', { 
                    original: networkId, 
                    cleaned: cleanNetworkId, 
                    originalLength: networkId.length, 
                    cleanedLength: cleanNetworkId.length,
                    originalCharCodes: networkId.split('').map(c => c.charCodeAt(0)),
                    isValidFormat: /^[0-9a-fA-F]{16}$/.test(cleanNetworkId)
                });
                
                // Validate: must be exactly 16 hex characters
                if (!cleanNetworkId || cleanNetworkId.length !== 16 || !/^[0-9a-fA-F]{16}$/i.test(cleanNetworkId)) {
                    networkIdInput.classList.add('zt-input-error');
                    setTimeout(() => {
                        networkIdInput.classList.remove('zt-input-error');
                    }, 2000);
                    const errorMsg = cleanNetworkId.length === 0 
                        ? 'Network ID cannot be empty.'
                        : cleanNetworkId.length !== 16
                        ? 'Invalid network ID format. Network ID must be exactly 16 hexadecimal characters (got ' + cleanNetworkId.length + ').'
                        : 'Invalid network ID format. Network ID must contain only hexadecimal characters (0-9, a-f).';
                    showMessage(errorMsg, 'error');
                    debugLog('Validation failed', { 
                        reason: cleanNetworkId.length === 0 ? 'Empty' : cleanNetworkId.length !== 16 ? 'Length mismatch' : 'Invalid characters',
                        expected: 16, 
                        actual: cleanNetworkId.length,
                        cleaned: cleanNetworkId
                    });
                    return;
                }
                
                joinNetwork(cleanNetworkId);
            });
        }
        
        // Load initial data
        loadNodeInfo();
        loadNetworks();
        
        // Auto-refresh networks every 30 seconds (without page reload)
        setInterval(loadNetworks, 30000);
    });
})();
</script>
