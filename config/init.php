<?php
/**
 * Initialize application - ensures required directories exist with proper permissions
 * This file should be included early in the application lifecycle
 */

function ensureInvoicesDirectory() {
    $invoice_dir = __DIR__ . '/../invoices/';
    
    // Create directory if it doesn't exist
    if (!file_exists($invoice_dir)) {
        // Try to create with different permission levels (most permissive first)
        $permissions = [0777, 0755, 0700];
        $created = false;
        
        foreach ($permissions as $perm) {
            if (@mkdir($invoice_dir, $perm, true)) {
                $created = true;
                // Set umask to ensure permissions are set correctly
                $old_umask = umask(0);
                @chmod($invoice_dir, $perm);
                umask($old_umask);
                break;
            }
        }
        
        if (!$created) {
            // Last resort: try to create parent directories first
            $parent_dir = dirname($invoice_dir);
            if (!file_exists($parent_dir)) {
                @mkdir($parent_dir, 0755, true);
            }
            // Try with maximum permissions
            $old_umask = umask(0);
            @mkdir($invoice_dir, 0777, true);
            @chmod($invoice_dir, 0777);
            umask($old_umask);
        }
    }
    
    // Ensure directory is writable (even if it already existed)
    if (file_exists($invoice_dir)) {
        // Try to set permissions (most permissive first)
        $permissions = [0777, 0755, 0700];
        $old_umask = umask(0);
        
        foreach ($permissions as $perm) {
            if (@chmod($invoice_dir, $perm)) {
                if (is_writable($invoice_dir)) {
                    umask($old_umask);
                    break;
                }
            }
        }
        
        // If still not writable, try to change ownership (if possible)
        if (!is_writable($invoice_dir)) {
            // Get current user (web server user)
            $current_user = get_current_user();
            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                $user_info = posix_getpwuid(posix_geteuid());
                if ($user_info) {
                    $current_user = $user_info['name'];
                }
            }
            
            // Try to chown (may require sudo, but worth trying)
            @chown($invoice_dir, $current_user);
            
            // Try one more time with 0777
            @chmod($invoice_dir, 0777);
        }
        
        umask($old_umask);
    }
    
    // Create .htaccess file to allow access (if using Apache)
    $htaccess_file = $invoice_dir . '.htaccess';
    if (!file_exists($htaccess_file) && is_writable($invoice_dir)) {
        $htaccess_content = "# Allow access to invoice files\n";
        $htaccess_content .= "Options +Indexes\n";
        $htaccess_content .= "<FilesMatch \"\\.(html|pdf)$\">\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        @file_put_contents($htaccess_file, $htaccess_content);
    }
    
    return is_writable($invoice_dir);
}

// Auto-initialize on include
ensureInvoicesDirectory();

