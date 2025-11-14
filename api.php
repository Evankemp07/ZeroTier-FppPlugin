<?php
/*
A way for plugins to provide their own PHP API endpoints.

To use, create a file called api.php file in the plugin's directory
and provide a getEndpointsPLUGINNAME() function which returns an
array describing the endpoints the plugin implements.  Since PHP
does not allow hyphens in function names, any hyphens in the plugin
name must be removed when substituting for PLUGINNAME above and if
the plugin name is used in any callback function names.  It is
also best to use unique endpoint names as shown below to eliminate
any conflicts with stock FPP code or other plugin API callbacks.

All endpoints are prefixed with /api/plugin/PLUGIN-NAME but only
the part after PLUGIN-NAME is specified in the getEndpointsPLUGINNAME()
data.  The plugin name is used as-is in the endpoint URL, hyphens
are not removed.  -- limonade.php is used for the underlying implementation so
param("param1" ) can be used for an api like /api/plugin/fpp-BigButtons/:param1

Here is a simple example which would add a
/api/plugin/fpp-BigButtons/version endpoint to the fpp-Bigbuttons plugin.
*/


function getEndpointsfpppluginZeroTier() {
    $result = array();

    $ep = array(
        'method' => 'GET',
        'endpoint' => 'info',
        'callback' => 'fpppluginZeroTierInfo');
    array_push($result, $ep);

    $ep = array(
        'method' => 'GET',
        'endpoint' => 'networks',
        'callback' => 'fpppluginZeroTierNetworks');
    array_push($result, $ep);

    $ep = array(
        'method' => 'POST',
        'endpoint' => 'join',
        'callback' => 'fpppluginZeroTierJoin');
    array_push($result, $ep);

        $ep = array(
            'method' => 'POST',
            'endpoint' => 'leave',
            'callback' => 'fpppluginZeroTierLeave');
        array_push($result, $ep);

        $ep = array(
            'method' => 'GET',
            'endpoint' => 'check-update',
            'callback' => 'fpppluginZeroTierCheckUpdate');
        array_push($result, $ep);

        $ep = array(
            'method' => 'POST',
            'endpoint' => 'update',
            'callback' => 'fpppluginZeroTierUpdate');
        array_push($result, $ep);

        return $result;
    }

// Helper function to run shownet script
function runShownetScript($action, $networkId = null) {
    $output = array();
    $return_var = 0;
    
    // Find shownet script path - try multiple locations
    $scriptPath = null;
    
    // Build list of potential paths to check
    $pluginPaths = array();
    
    // Current file's directory (works for both local dev and FPP)
    $currentDir = dirname(__FILE__);
    $pluginPaths[] = $currentDir . '/scripts/shownet.sh';
    
    // Try relative to current directory (for local dev)
    $pluginPaths[] = dirname(dirname(__FILE__)) . '/scripts/shownet.sh';
    
    // Also try if $settings is available (FPP context)
    global $settings;
    if (isset($settings) && isset($settings['pluginDirectory'])) {
        $pluginDir = $settings['pluginDirectory'];
        // Try with plugin name subdirectory (standard FPP structure)
        $pluginPaths[] = $pluginDir . '/fpp-plugin-ZeroTier/scripts/shownet.sh';
        // Also try directly in pluginDirectory (if pluginDirectory already points to plugin folder)
        $pluginPaths[] = $pluginDir . '/scripts/shownet.sh';
    }
    
    // Try FPP plugin directory structure (common locations)
    $pluginPaths[] = '/home/fpp/media/plugins/fpp-plugin-ZeroTier/scripts/shownet.sh';
    $pluginPaths[] = '/opt/fpp/media/plugins/fpp-plugin-ZeroTier/scripts/shownet.sh';
    $pluginPaths[] = '/media/fpp/plugins/fpp-plugin-ZeroTier/scripts/shownet.sh';
    
    // Check plugin directory paths first (for local dev and FPP)
    foreach ($pluginPaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $scriptPath = $path;
            break;
        }
    }
    
    // If not found in plugin directories, try the installed location (from fpp_install.sh)
    if (!$scriptPath && file_exists('/usr/local/bin/shownet') && is_executable('/usr/local/bin/shownet')) {
        $scriptPath = '/usr/local/bin/shownet';
    }
    
    if (!$scriptPath || !file_exists($scriptPath)) {
        return array('output' => array('shownet script not found. Checked: ' . implode(', ', $pluginPaths) . ', /usr/local/bin/shownet'), 'return' => 1, 'error' => 'shownet script not found');
    }
    
    // Build command with environment variable
    $command = '';
    if ($networkId) {
        $command = 'ZEROTIER_NETWORK_ID=' . escapeshellarg($networkId) . ' ';
    }
    
    // Use the script directly if it's executable, otherwise use bash
    if (is_executable($scriptPath) && $scriptPath === '/usr/local/bin/shownet') {
        // For installed shownet, call it directly
        $command .= escapeshellarg($scriptPath) . ' ' . escapeshellarg($action) . ' 2>&1';
    } else {
        // For .sh files, use bash
        $command .= 'bash ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($action) . ' 2>&1';
    }
    
    // Execute command
    exec($command, $output, $return_var);
    
    return array('output' => $output, 'return' => $return_var, 'command' => $command, 'script_path' => $scriptPath);
}

// Helper function to run ZeroTier CLI
function runZerotierCLI($command) {
    $output = array();
    $return_var = 0;
    
    // Find zerotier-cli path
    $ztPath = trim(shell_exec("which zerotier-cli 2>/dev/null"));
    if (empty($ztPath)) {
        // Try common locations
        $commonPaths = array(
            '/usr/bin/zerotier-cli',
            '/usr/local/bin/zerotier-cli',
            '/opt/homebrew/bin/zerotier-cli'
        );
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                $ztPath = $path;
                break;
            }
        }
    }
    
    if (empty($ztPath)) {
        return array('output' => array('zerotier-cli not found'), 'return' => 1, 'error' => 'zerotier-cli not found in PATH');
    }
    
    // Try without sudo first
    $fullCommand = escapeshellarg($ztPath) . " " . escapeshellarg($command) . " 2>&1";
    exec($fullCommand, $output, $return_var);
    
    // Check if output contains permission errors (even if exit code is 0)
    $outputStr = implode(' ', $output);
    $hasPermissionError = (strpos($outputStr, 'authtoken.secret not found') !== false || 
                          strpos($outputStr, 'try again as root') !== false ||
                          strpos($outputStr, 'Permission denied') !== false);
    
    // If that fails or has permission errors, try with sudo
    if ($return_var != 0 || $hasPermissionError) {
        $fullCommand = "sudo " . escapeshellarg($ztPath) . " " . escapeshellarg($command) . " 2>&1";
        exec($fullCommand, $output, $return_var);
    }
    
    return array('output' => $output, 'return' => $return_var, 'command' => $fullCommand);
}

// GET /api/plugin/fpp-plugin-ZeroTier/info
function fpppluginZeroTierInfo() {
    $result = array();
    $nodeInfo = runZerotierCLI("info");
    
    if ($nodeInfo['return'] == 0 && !empty($nodeInfo['output'])) {
        $infoLine = trim(implode(' ', $nodeInfo['output'])); // Join all output lines
        
        // Check if the output looks like a valid ZeroTier info response
        // Format should be: "200 info <nodeid> <version> <status>"
        // Node ID is 10 hex characters, version is like "1.16.0" or "1.16.0-2", status is ONLINE/OFFLINE/etc
        // Note: Sometimes error messages are prepended, so we search for the pattern anywhere in the line
        if (preg_match('/(\d+)\s+info\s+([0-9a-f]{10})\s+([0-9.]+(?:-[0-9]+)?)\s+(\w+)/i', $infoLine, $matches)) {
            $result['success'] = true;
            $result['nodeId'] = $matches[2];
            $result['version'] = $matches[3];
            // Normalize status: convert to uppercase and ensure "OFFLINE" for offline status
            $rawStatus = trim($matches[4]);
            $status = strtoupper($rawStatus);
            // If status is not "ONLINE", set it to "OFFLINE"
            if ($status === 'ONLINE') {
                $result['status'] = 'ONLINE';
            } else {
                $result['status'] = 'OFFLINE';
            }
            $result['statusCode'] = $matches[1];
            $result['command'] = 'info';
        } else {
            // Invalid format - likely an error message
            $result['success'] = false;
            $result['error'] = 'Invalid ZeroTier response format: ' . htmlspecialchars($infoLine);
            $result['output'] = $nodeInfo['output'];
            return json($result);
        }
        
        // Also get network list for complete info
        $networkList = runZerotierCLI("listnetworks");
        if ($networkList['return'] == 0) {
                $networks = array();
                foreach ($networkList['output'] as $line) {
                    // Match lines that contain a 16-character hex network ID (skip header lines)
                    if (preg_match('/\b([0-9a-f]{16})\s+(.+?)\s+([0-9a-f]{2}(?::[0-9a-f]{2}){5})\s+(\w+)\s+(\w+)\s+(\S+)\s+(.+)$/i', $line, $matches)) {
                        $networks[] = array(
                            'networkId' => $matches[1],
                            'name' => trim($matches[2]),
                            'mac' => $matches[3],
                            'status' => $matches[4],
                            'type' => $matches[5],
                            'interface' => $matches[6],
                            'ip' => trim($matches[7])
                        );
                    } elseif (preg_match('/\b([0-9a-f]{16})\s+/i', $line, $idMatch)) {
                        // Fallback: simpler parsing for lines with network ID
                        $parts = preg_split('/\s+/', trim($line));
                        // Find the network ID position
                        $networkIdIndex = -1;
                        foreach ($parts as $idx => $part) {
                            if (preg_match('/^[0-9a-f]{16}$/i', $part)) {
                                $networkIdIndex = $idx;
                                break;
                            }
                        }
                        if ($networkIdIndex >= 0 && count($parts) > $networkIdIndex + 5) {
                            $networks[] = array(
                                'networkId' => $parts[$networkIdIndex],
                                'name' => isset($parts[$networkIdIndex + 1]) ? $parts[$networkIdIndex + 1] : '',
                                'status' => isset($parts[$networkIdIndex + 4]) ? $parts[$networkIdIndex + 4] : '',
                                'type' => isset($parts[$networkIdIndex + 5]) ? $parts[$networkIdIndex + 5] : '',
                                'interface' => isset($parts[$networkIdIndex + 6]) ? $parts[$networkIdIndex + 6] : '',
                                'ip' => isset($parts[$networkIdIndex + 7]) ? $parts[$networkIdIndex + 7] : ''
                            );
                        }
                    }
                }
                $result['networks'] = $networks;
            }
    } else {
        $result['success'] = false;
        $result['error'] = 'ZeroTier CLI not available';
        if (isset($nodeInfo['error'])) {
            $result['error'] = $nodeInfo['error'];
        }
        $result['output'] = isset($nodeInfo['output']) ? $nodeInfo['output'] : array();
    }
    
    return json($result);
}

// GET /api/plugin/fpp-plugin-ZeroTier/networks
function fpppluginZeroTierNetworks() {
    $result = array();
    $networkList = runZerotierCLI("listnetworks");
    
    if ($networkList['return'] == 0) {
        $networks = array();
        foreach ($networkList['output'] as $line) {
            // Match lines that contain a 16-character hex network ID (skip header lines)
            if (preg_match('/\b([0-9a-f]{16})\s+(.+?)\s+([0-9a-f]{2}(?::[0-9a-f]{2}){5})\s+(\w+)\s+(\w+)\s+(\S+)\s+(.+)$/i', $line, $matches)) {
                $networks[] = array(
                    'networkId' => $matches[1],
                    'name' => trim($matches[2]),
                    'mac' => $matches[3],
                    'status' => $matches[4],
                    'type' => $matches[5],
                    'interface' => $matches[6],
                    'ip' => trim($matches[7])
                );
            } elseif (preg_match('/\b([0-9a-f]{16})\s+/i', $line, $idMatch)) {
                // Fallback: simpler parsing for lines with network ID
                $parts = preg_split('/\s+/', trim($line));
                // Find the network ID position
                $networkIdIndex = -1;
                foreach ($parts as $idx => $part) {
                    if (preg_match('/^[0-9a-f]{16}$/i', $part)) {
                        $networkIdIndex = $idx;
                        break;
                    }
                }
                if ($networkIdIndex >= 0 && count($parts) > $networkIdIndex + 5) {
                    $networks[] = array(
                        'networkId' => $parts[$networkIdIndex],
                        'name' => isset($parts[$networkIdIndex + 1]) ? $parts[$networkIdIndex + 1] : '',
                        'status' => isset($parts[$networkIdIndex + 4]) ? $parts[$networkIdIndex + 4] : '',
                        'type' => isset($parts[$networkIdIndex + 5]) ? $parts[$networkIdIndex + 5] : '',
                        'interface' => isset($parts[$networkIdIndex + 6]) ? $parts[$networkIdIndex + 6] : '',
                        'ip' => isset($parts[$networkIdIndex + 7]) ? $parts[$networkIdIndex + 7] : ''
                    );
                }
            }
        }
        $result['success'] = true;
        $result['networks'] = $networks;
    } else {
        $result['success'] = false;
        $result['error'] = 'Could not list networks';
        $result['output'] = $networkList['output'];
    }
    
    return json($result);
}

// POST /api/plugin/fpp-plugin-ZeroTier/join
function fpppluginZeroTierJoin() {
    $result = array();
    
    // Handle both form data and JSON POST data
    $networkId = null;
    
    // First try reading from POST body (JSON) - this is what the frontend sends
    $postData = file_get_contents('php://input');
    if (!empty($postData)) {
        $jsonData = json_decode($postData, true);
        if ($jsonData && isset($jsonData['networkId'])) {
            $networkId = $jsonData['networkId'];
        }
    }
    
    // Fallback to param() function (for form data)
    if (empty($networkId)) {
        $networkId = param('networkId');
    }
    
    // Also try network_id (form field name)
    if (empty($networkId)) {
        $networkId = param('network_id');
    }
    
    // Clean and validate network ID
    $originalNetworkId = $networkId;
    if (!empty($networkId)) {
        // Remove all non-hexadecimal characters (spaces, dashes, etc.)
        $networkId = preg_replace('/[^0-9a-fA-F]/', '', trim($networkId));
    }
    
    // Debug info
    $result['debug'] = array(
        'original' => $originalNetworkId ? htmlspecialchars($originalNetworkId) : '(empty)',
        'cleaned' => $networkId ? htmlspecialchars($networkId) : '(empty)',
        'original_length' => $originalNetworkId ? strlen($originalNetworkId) : 0,
        'cleaned_length' => $networkId ? strlen($networkId) : 0,
        'is_valid_format' => $networkId && preg_match('/^[0-9a-f]{16}$/i', $networkId)
    );
    
    if (!$networkId || strlen($networkId) !== 16 || !preg_match('/^[0-9a-f]{16}$/i', $networkId)) {
        $result['success'] = false;
        if (empty($networkId)) {
            $result['error'] = 'Network ID cannot be empty.';
        } elseif (strlen($networkId) !== 16) {
            $result['error'] = 'Invalid network ID format. Expected exactly 16 hexadecimal characters, got ' . strlen($networkId) . '.';
        } else {
            $result['error'] = 'Invalid network ID format. Network ID must contain only hexadecimal characters (0-9, a-f).';
        }
        return json($result);
    }
    
    // Use shownet script to join network
    $joinResult = runShownetScript('join', $networkId);
    
    // Check exit code - 0 means success
    if ($joinResult['return'] == 0) {
        $result['success'] = true;
        $result['message'] = 'Successfully joined network';
        $result['output'] = $joinResult['output'];
    } else {
        $result['success'] = false;
        $errorMsg = 'Failed to join network';
        if (isset($joinResult['error'])) {
            $errorMsg = $joinResult['error'];
        }
        $result['error'] = $errorMsg;
        $result['output'] = $joinResult['output'];
        if (isset($joinResult['script_path'])) {
            $result['script_path'] = $joinResult['script_path'];
        }
        if (isset($joinResult['command'])) {
            $result['command'] = $joinResult['command'];
        }
    }
    
    return json($result);
}

// POST /api/plugin/fpp-plugin-ZeroTier/leave
function fpppluginZeroTierLeave() {
    $result = array();
    
    // Handle both form data and JSON POST data
    $networkId = null;
    
    // First try reading from POST body (JSON) - this is what the frontend sends
    $postData = file_get_contents('php://input');
    if (!empty($postData)) {
        $jsonData = json_decode($postData, true);
        if ($jsonData && isset($jsonData['networkId'])) {
            $networkId = $jsonData['networkId'];
        }
    }
    
    // Fallback to param() function (for form data)
    if (empty($networkId)) {
        $networkId = param('networkId');
    }
    
    // Also try network_id (form field name)
    if (empty($networkId)) {
        $networkId = param('network_id');
    }
    
    // Clean and validate network ID
    $originalNetworkId = $networkId;
    if (!empty($networkId)) {
        // Remove all non-hexadecimal characters (spaces, dashes, etc.)
        $networkId = preg_replace('/[^0-9a-fA-F]/', '', trim($networkId));
    }
    
    // Debug info
    $result['debug'] = array(
        'original' => $originalNetworkId ? htmlspecialchars($originalNetworkId) : '(empty)',
        'cleaned' => $networkId ? htmlspecialchars($networkId) : '(empty)',
        'original_length' => $originalNetworkId ? strlen($originalNetworkId) : 0,
        'cleaned_length' => $networkId ? strlen($networkId) : 0,
        'is_valid_format' => $networkId && preg_match('/^[0-9a-f]{16}$/i', $networkId)
    );
    
    if (!$networkId || strlen($networkId) !== 16 || !preg_match('/^[0-9a-f]{16}$/i', $networkId)) {
        $result['success'] = false;
        if (empty($networkId)) {
            $result['error'] = 'Network ID cannot be empty.';
        } elseif (strlen($networkId) !== 16) {
            $result['error'] = 'Invalid network ID format. Expected exactly 16 hexadecimal characters, got ' . strlen($networkId) . '.';
        } else {
            $result['error'] = 'Invalid network ID format. Network ID must contain only hexadecimal characters (0-9, a-f).';
        }
        return json($result);
    }
    
    // Use shownet script to leave network
    $leaveResult = runShownetScript('leave', $networkId);
    
    // Check exit code - 0 means success
    if ($leaveResult['return'] == 0) {
        $result['success'] = true;
        $result['message'] = 'Successfully left network';
        $result['output'] = $leaveResult['output'];
    } else {
        $result['success'] = false;
        $errorMsg = 'Failed to leave network';
        if (isset($leaveResult['error'])) {
            $errorMsg = $leaveResult['error'];
        }
        $result['error'] = $errorMsg;
        $result['output'] = $leaveResult['output'];
        if (isset($leaveResult['script_path'])) {
            $result['script_path'] = $leaveResult['script_path'];
        }
        if (isset($leaveResult['command'])) {
            $result['command'] = $leaveResult['command'];
        }
    }
    
    return json($result);
    }

    // GET /api/plugin/fpp-plugin-ZeroTier/check-update
    function fpppluginZeroTierCheckUpdate() {
        $result = array();
        
        // Get current version using the same parsing logic as fpppluginZeroTierInfo
        $nodeInfo = runZerotierCLI("info");
        $currentVersion = '';
        
        if ($nodeInfo['return'] == 0 && !empty($nodeInfo['output'])) {
            $infoLine = trim(implode(' ', $nodeInfo['output'])); // Join all output lines
            
            // Use the same regex pattern as fpppluginZeroTierInfo to extract version
            if (preg_match('/(\d+)\s+info\s+([0-9a-f]{10})\s+([0-9.]+(?:-[0-9]+)?)\s+(\w+)/i', $infoLine, $matches)) {
                $currentVersion = $matches[3]; // Version is in capture group 3
            }
        }
        
        if (empty($currentVersion)) {
            $result['success'] = false;
            $result['error'] = 'Could not determine current version';
            $result['output'] = isset($nodeInfo['output']) ? $nodeInfo['output'] : array();
            return json($result);
        }
        
        $result['currentVersion'] = $currentVersion;
        $result['updateAvailable'] = false;
        $result['latestVersion'] = '';
        
        // Check for updates based on OS
        $os = php_uname('s');
        
        if (strpos(strtolower($os), 'darwin') !== false || strpos(strtolower($os), 'mac') !== false) {
            // macOS - check via Homebrew
            $brewPath = trim(shell_exec("which brew 2>/dev/null"));
            if (!empty($brewPath)) {
                $brewInfo = shell_exec("brew info zerotier-one 2>&1");
                if (preg_match('/==> zerotier-one: ([0-9.]+(?:-[0-9]+)?)/', $brewInfo, $matches)) {
                    $latestVersion = $matches[1];
                    $result['latestVersion'] = $latestVersion;
                    // Normalize versions for comparison (remove any build suffixes)
                    $currentNormalized = preg_replace('/-.*$/', '', $currentVersion);
                    $latestNormalized = preg_replace('/-.*$/', '', $latestVersion);
                    if (version_compare($currentNormalized, $latestNormalized, '<')) {
                        $result['updateAvailable'] = true;
                    }
                }
            }
        } elseif (strpos(strtolower($os), 'linux') !== false) {
            // Linux - check via package manager
            // Try apt (Debian/Ubuntu)
            $aptCheck = shell_exec("apt-cache policy zerotier-one 2>&1 | grep 'Candidate:' | awk '{print \$2}'");
            if (!empty($aptCheck) && trim($aptCheck) !== '' && trim($aptCheck) !== '(none)') {
                $latestVersion = trim($aptCheck);
                // Remove any epoch or architecture suffixes (e.g., "1.16.0-2" or "1:1.16.0")
                $latestVersion = preg_replace('/^[0-9]+:/', '', $latestVersion); // Remove epoch
                $latestVersion = preg_replace('/:[^:]*$/', '', $latestVersion); // Remove architecture
                // Extract just the version number part (before any dash)
                $latestVersion = preg_replace('/-.*$/', '', $latestVersion);
                
                if (!empty($latestVersion)) {
                    $result['latestVersion'] = $latestVersion;
                    // Normalize versions for comparison - compare base versions only
                    $currentNormalized = preg_replace('/-.*$/', '', $currentVersion);
                    if (version_compare($currentNormalized, $latestVersion, '<')) {
                        $result['updateAvailable'] = true;
                    }
                }
            } else {
                // Try yum (RHEL/CentOS)
                $yumCheck = shell_exec("yum list available zerotier-one 2>&1 | grep -E '^zerotier-one' | awk '{print \$2}' | head -1");
                if (!empty($yumCheck) && trim($yumCheck) !== '' && trim($yumCheck) !== '(none)') {
                    $latestVersion = trim($yumCheck);
                    // Remove epoch if present and extract version
                    $latestVersion = preg_replace('/^[0-9]+:/', '', $latestVersion);
                    $latestVersion = preg_replace('/-.*$/', '', $latestVersion);
                    
                    if (!empty($latestVersion)) {
                        $result['latestVersion'] = $latestVersion;
                        // Normalize versions for comparison
                        $currentNormalized = preg_replace('/-.*$/', '', $currentVersion);
                        if (version_compare($currentNormalized, $latestVersion, '<')) {
                            $result['updateAvailable'] = true;
                        }
                    }
                }
            }
        }
        
        $result['success'] = true;
        return json($result);
    }

    // POST /api/plugin/fpp-plugin-ZeroTier/update
    function fpppluginZeroTierUpdate() {
        $result = array();
        
        $os = php_uname('s');
        
        if (strpos(strtolower($os), 'darwin') !== false || strpos(strtolower($os), 'mac') !== false) {
            // macOS - update via Homebrew
            $brewPath = trim(shell_exec("which brew 2>/dev/null"));
            if (!empty($brewPath)) {
                // Run update command (no sudo needed for brew)
                $updateOutput = shell_exec("brew upgrade zerotier-one 2>&1");
                $result['success'] = true;
                $result['message'] = 'ZeroTier updated successfully';
                $result['output'] = $updateOutput;
            } else {
                $result['success'] = false;
                $result['error'] = 'Homebrew not found';
            }
        } elseif (strpos(strtolower($os), 'linux') !== false) {
            // Linux - update via package manager
            // Try without sudo first (in case user has permissions)
            $updateOutput = '';
            $success = false;
            
            if (shell_exec("which apt-get 2>/dev/null")) {
                // Debian/Ubuntu - try without sudo first
                $updateOutput = shell_exec("apt-get update 2>&1 && apt-get upgrade -y zerotier-one 2>&1");
                $success = true;
                
                // If that fails, try with sudo (may require passwordless sudo)
                if (empty($updateOutput) || strpos($updateOutput, 'Permission denied') !== false) {
                    $updateOutput = shell_exec("sudo apt-get update 2>&1 && sudo apt-get upgrade -y zerotier-one 2>&1");
                    $success = !empty($updateOutput) && strpos($updateOutput, 'Permission denied') === false;
                }
            } elseif (shell_exec("which yum 2>/dev/null")) {
                // RHEL/CentOS - try without sudo first
                $updateOutput = shell_exec("yum update -y zerotier-one 2>&1");
                $success = true;
                
                // If that fails, try with sudo
                if (empty($updateOutput) || strpos($updateOutput, 'Permission denied') !== false) {
                    $updateOutput = shell_exec("sudo yum update -y zerotier-one 2>&1");
                    $success = !empty($updateOutput) && strpos($updateOutput, 'Permission denied') === false;
                }
            } else {
                $result['success'] = false;
                $result['error'] = 'Package manager not found';
                return json($result);
            }
            
            if ($success) {
                $result['success'] = true;
                $result['message'] = 'ZeroTier updated successfully';
                $result['output'] = $updateOutput;
            } else {
                $result['success'] = false;
                $result['error'] = 'Update failed. You may need to configure passwordless sudo for the web server user, or run updates manually via SSH.';
                $result['output'] = $updateOutput;
            }
        } else {
            $result['success'] = false;
            $result['error'] = 'Unsupported OS';
        }
        
        return json($result);
    }

    ?>
