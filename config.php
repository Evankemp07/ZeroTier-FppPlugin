<?php
// ZeroTier Network Configuration Page

$pluginName = "ZeroTier-FppPlugin";
$pluginPath = $settings['pluginDirectory'] . "/" . $pluginName;

// Get initial data via API (for initial page load, we'll use PHP to get node info)
// The networks will be loaded via AJAX
$nodeId = '';
$nodeInfo = array();
?>

<div class="zerotier-config">
    <fieldset style="padding: 10px; border: 2px solid #000;">
        <legend>ZeroTier Network Configuration</legend>
        
        <div id="message-container"></div>
        
        <div style="padding: 10px;">
            <h3>Join a Network</h3>
            <div id="zerotier-status-check">
                <p>Checking ZeroTier status...</p>
            </div>
            <div id="join-form-container" style="display: none;">
                <form id="join-network-form">
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 5px; width: 150px;">
                                <label for="join_network_id">Network ID:</label>
                            </td>
                            <td style="padding: 5px;">
                                <input type="text" id="join_network_id" name="network_id" 
                                       pattern="[0-9a-fA-F\s-]{16,}" 
                                       placeholder="16 hexadecimal characters (spaces/dashes OK)"
                                       style="width: 300px; font-family: monospace;"
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 5px;">
                                <button type="submit" id="join-btn" style="padding: 5px 15px;">Join Network</button>
                            </td>
                        </tr>
                    </table>
                </form>
                <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                    <strong>Note:</strong> After joining, you may need to authorize this node in the ZeroTier web interface.
                    <br>Your Node ID: <code id="node-id-display">Loading...</code>
                </p>
            </div>
        </div>
        
        <div style="padding: 10px; margin-top: 20px;">
            <h3>Current Networks</h3>
            <div id="networks-container">
                <p>Loading networks...</p>
            </div>
        </div>
        
        <div id="quick-links-container" style="padding: 10px; margin-top: 20px; background-color: #f0f0f0; border: 1px solid #ccc; display: none;">
            <h3>Quick Links</h3>
            <p>
                <a href="https://my.zerotier.com/" target="_blank">ZeroTier Central</a> |
                <a href="https://my.zerotier.com/network" target="_blank">My Networks</a>
            </p>
            <p>
                <strong>Your Node ID:</strong> <code id="node-id-quick-links">Loading...</code>
            </p>
        </div>
    </fieldset>
</div>

<script>
(function() {
    const API_BASE = '/api/plugin/ZeroTier-FppPlugin';
    
    // Utility function to show messages
    function showMessage(message, type) {
        const container = document.getElementById('message-container');
        const bgColor = type === 'success' ? '#ccffcc' : '#ffcccc';
        const borderColor = type === 'success' ? '#00cc00' : '#ff0000';
        container.innerHTML = '<div style="padding: 10px; margin: 10px 0; background-color: ' + bgColor + '; border: 1px solid ' + borderColor + ';">' + 
                              escapeHtml(message) + '</div>';
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                container.style.transition = 'opacity 0.5s';
                container.style.opacity = '0';
                setTimeout(function() {
                    container.innerHTML = '';
                    container.style.opacity = '1';
                }, 500);
            }, 5000);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Load node info
    function loadNodeInfo() {
        fetch(API_BASE + '/info')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('node-id-display').textContent = data.nodeId || 'Unknown';
                    document.getElementById('node-id-quick-links').textContent = data.nodeId || 'Unknown';
                    if (data.nodeId) {
                        document.getElementById('quick-links-container').style.display = 'block';
                    }
                    
                    // Show join form if ZeroTier is available
                    document.getElementById('zerotier-status-check').style.display = 'none';
                    document.getElementById('join-form-container').style.display = 'block';
                } else {
                    document.getElementById('zerotier-status-check').innerHTML = 
                        '<div style="padding: 10px; background-color: #ffcccc; border: 1px solid #ff0000;">' +
                        '<strong>ZeroTier CLI not found.</strong> Please ensure ZeroTier is installed.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading node info:', error);
                document.getElementById('zerotier-status-check').innerHTML = 
                    '<div style="padding: 10px; background-color: #ffcccc; border: 1px solid #ff0000;">' +
                    '<strong>Error:</strong> Could not connect to API. ' + escapeHtml(error.message) + '</div>';
            });
    }
    
    // Load networks list
    function loadNetworks() {
        fetch(API_BASE + '/networks')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('networks-container');
                
                if (!data.success) {
                    container.innerHTML = 
                        '<div style="padding: 10px; background-color: #ffeeee; border: 1px solid #ff0000;">' +
                        '<strong>Could not retrieve network list.</strong><br>' +
                        (data.error ? escapeHtml(data.error) : 'Unknown error') +
                        '<br><br>Try running from command line: <code>zerotier-cli listnetworks</code> or <code>shownet info</code></div>';
                    return;
                }
                
                if (!data.networks || data.networks.length === 0) {
                    container.innerHTML = '<p>Not currently joined to any networks.</p>';
                    return;
                }
                
                let html = '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">' +
                    '<thead>' +
                    '<tr style="background-color: #f0f0f0;">' +
                    '<th style="padding: 8px; text-align: left; border: 1px solid #ccc;">Network ID</th>' +
                    '<th style="padding: 8px; text-align: left; border: 1px solid #ccc;">Name</th>' +
                    '<th style="padding: 8px; text-align: left; border: 1px solid #ccc;">Status</th>' +
                    '<th style="padding: 8px; text-align: left; border: 1px solid #ccc;">IP Address</th>' +
                    '<th style="padding: 8px; text-align: left; border: 1px solid #ccc;">Actions</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';
                
                data.networks.forEach(function(network) {
                    const statusColor = network.status === 'OK' ? '#00cc00' : '#ffaa00';
                    const ipDisplay = (network.ip && network.ip !== '-') ? escapeHtml(network.ip) : '<span style="color: #999;">Not assigned</span>';
                    
                    html += '<tr>' +
                        '<td style="padding: 8px; border: 1px solid #ccc; font-family: monospace;">' + escapeHtml(network.networkId) + '</td>' +
                        '<td style="padding: 8px; border: 1px solid #ccc;">' + escapeHtml(network.name || '-') + '</td>' +
                        '<td style="padding: 8px; border: 1px solid #ccc;">' +
                        '<span style="color: ' + statusColor + '; font-weight: bold;">' + escapeHtml(network.status) + '</span>' +
                        '</td>' +
                        '<td style="padding: 8px; border: 1px solid #ccc; font-family: monospace;">' + ipDisplay + '</td>' +
                        '<td style="padding: 8px; border: 1px solid #ccc;">' +
                        '<button onclick="leaveNetwork(\'' + escapeHtml(network.networkId) + '\')" ' +
                        'style="padding: 3px 10px; background-color: #ff6666; color: white; border: none; cursor: pointer;">Leave</button> ' +
                        '<a href="https://my.zerotier.com/network/' + escapeHtml(network.networkId) + '" ' +
                        'target="_blank" ' +
                        'style="margin-left: 5px; padding: 3px 10px; background-color: #6666ff; color: white; text-decoration: none; display: inline-block;">Manage</a>' +
                        '</td>' +
                        '</tr>';
                });
                
                html += '</tbody></table>';
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading networks:', error);
                document.getElementById('networks-container').innerHTML = 
                    '<div style="padding: 10px; background-color: #ffeeee; border: 1px solid #ff0000;">' +
                    '<strong>Error:</strong> Could not load networks. ' + escapeHtml(error.message) + '</div>';
            });
    }
    
    // Join network
    function joinNetwork(networkId) {
        const btn = document.getElementById('join-btn');
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
            btn.disabled = false;
            btn.textContent = originalText;
            
            if (data.success) {
                showMessage('Successfully joined network: ' + networkId, 'success');
                document.getElementById('join_network_id').value = '';
                // Reload networks list
                setTimeout(loadNetworks, 1000);
            } else {
                showMessage('Failed to join network: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
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
        
        fetch(API_BASE + '/leave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ networkId: networkId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Successfully left network: ' + networkId, 'success');
                // Reload networks list
                setTimeout(loadNetworks, 1000);
            } else {
                showMessage('Failed to leave network: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showMessage('Error leaving network: ' + error.message, 'error');
        });
    };
    
    // Handle join form submission
    document.addEventListener('DOMContentLoaded', function() {
        const joinForm = document.getElementById('join-network-form');
        if (joinForm) {
            joinForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const networkId = document.getElementById('join_network_id').value.trim();
                // Clean network ID (remove spaces/dashes)
                const cleanNetworkId = networkId.replace(/[^0-9a-fA-F]/g, '');
                
                if (cleanNetworkId.length !== 16) {
                    showMessage('Invalid network ID format. Network ID must be 16 hexadecimal characters.', 'error');
                    return;
                }
                
                joinNetwork(cleanNetworkId);
            });
        }
        
        // Load initial data
        loadNodeInfo();
        loadNetworks();
        
        // Auto-refresh networks every 30 seconds
        setInterval(loadNetworks, 30000);
    });
})();
</script>
