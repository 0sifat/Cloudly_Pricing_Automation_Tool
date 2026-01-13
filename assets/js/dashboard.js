// Dashboard JavaScript
let currentProjectId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Project form submission
    const projectForm = document.getElementById('projectForm');
    if (projectForm) {
        projectForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const projectName = document.getElementById('project_name').value;
            const salesmanName = document.getElementById('salesman_name').value;
            const projectRegion = document.getElementById('project_region').value;
            const projectId = document.getElementById('project_id').value;

            try {
                const formData = new FormData();
                formData.append('action', projectId ? 'update' : 'create');
                formData.append('project_name', projectName);
                formData.append('salesman_name', salesmanName);
                formData.append('region', projectRegion);
                if (projectId) {
                    formData.append('project_id', projectId);
                }

                const response = await fetch('../controllers/project_controller.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    currentProjectId = result.project_id;
                    document.getElementById('project_id').value = result.project_id;
                    alert('Project saved successfully!');
                    if (!projectId) {
                        window.location.href = `dashboard.php?project_id=${result.project_id}`;
                    }
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
    }

    // Initialize project ID
    const projectIdInput = document.getElementById('project_id');
    if (projectIdInput && projectIdInput.value) {
        currentProjectId = projectIdInput.value;
    }
    
    // Listen for region changes to refresh EC2/RDS instances
    const projectRegion = document.getElementById('project_region');
    if (projectRegion) {
        projectRegion.addEventListener('change', function() {
            // Clear cache when region changes
            if (typeof ec2InstanceOptions !== 'undefined') {
                ec2InstanceOptions = {};
            }
            if (typeof rdsInstanceOptions !== 'undefined') {
                rdsInstanceOptions = {};
            }
            // Re-render EC2 instances if EC2 form is visible
            if (document.getElementById('ec2InstancesList')) {
                if (typeof renderEC2Instances === 'function') {
                    renderEC2Instances();
                }
            }
            // Re-render RDS instances if RDS form is visible
            if (document.getElementById('rdsInstancesList')) {
                if (typeof renderRDSInstances === 'function') {
                    renderRDSInstances();
                }
            }
        });
    }

    // Service button clicks
    const serviceButtons = document.querySelectorAll('.service-btn');
    serviceButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const service = this.dataset.service;
            loadServiceForm(service);
            
            // Update active state
            serviceButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // If EBS is selected, refresh EC2 instances list
            if (service === 'ebs') {
                setTimeout(() => {
                    if (typeof renderEBSVolumes === 'function') {
                        renderEBSVolumes();
                    }
                }, 300);
            }
        });
    });

    // Calculate button
    const calculateBtn = document.getElementById('calculateBtn');
    if (calculateBtn) {
        calculateBtn.addEventListener('click', calculateTotalCost);
    }

    // Generate PDF button
    const generatePdfBtn = document.getElementById('generatePdfBtn');
    if (generatePdfBtn) {
        generatePdfBtn.addEventListener('click', generatePDF);
    }
});

function loadServiceForm(service) {
    const serviceForms = document.getElementById('serviceForms');
    
    // Hide welcome message
    const welcomeMsg = serviceForms.querySelector('.welcome-message');
    if (welcomeMsg) {
        welcomeMsg.style.display = 'none';
    }

    // Hide all service forms
    const allForms = serviceForms.querySelectorAll('.service-form');
    allForms.forEach(form => {
        form.classList.remove('active');
    });

    // Show selected service form
    let form = serviceForms.querySelector(`#${service}Form`);
    if (!form) {
        // Load service form dynamically
        loadServiceFormContent(service);
    } else {
        form.classList.add('active');
    }
}

// Make loadServiceForm globally available
window.loadServiceForm = loadServiceForm;

function loadServiceFormContent(service) {
    const serviceForms = document.getElementById('serviceForms');
    
    // Create form container
    const formDiv = document.createElement('div');
    formDiv.id = `${service}Form`;
    formDiv.className = 'service-form active';
    serviceForms.appendChild(formDiv);

    // Load service-specific form
    if (typeof window[`load${service.toUpperCase()}Form`] === 'function') {
        window[`load${service.toUpperCase()}Form`](formDiv);
    }
}

async function calculateTotalCost() {
    if (!currentProjectId) {
        alert('Please save your project first!');
        return;
    }

    try {
        const response = await fetch(`../controllers/calculate_controller.php?project_id=${currentProjectId}`);
        const result = await response.json();
        
        if (result.success) {
            displayCostSummary(result.summary);
        } else {
            alert('Error calculating costs: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

function displayCostSummary(summary) {
    const costSummary = document.getElementById('costSummary');
    const summaryContent = document.getElementById('summaryContent');
    
    let html = '<div class="summary-section">';
    html += '<h4>Total Estimated Cost</h4>';
    html += `<div class="summary-item"><span>Total Unit Cost:</span><span>$${summary.total_unit_cost.toFixed(2)}</span></div>`;
    html += `<div class="summary-item"><span>Total Estimated Cost:</span><span>$${summary.total_estimated_cost.toFixed(2)}</span></div>`;
    html += '</div>';

    if (summary.breakdown) {
        html += '<div class="summary-section" style="margin-top: 20px;">';
        html += '<h4>Service Breakdown</h4>';
        for (const [service, cost] of Object.entries(summary.breakdown)) {
            if (cost > 0) {
                html += `<div class="summary-item"><span>${service}:</span><span>$${cost.toFixed(2)}</span></div>`;
            }
        }
        html += '</div>';
    }

    summaryContent.innerHTML = html;
    costSummary.style.display = 'block';
}

async function generatePDF() {
    if (!currentProjectId) {
        alert('Please save your project first!');
        return;
    }

    // Show loading message
    const loadingMsg = document.createElement('div');
    loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 3000; text-align: center;';
    loadingMsg.innerHTML = '<h3>Generating PDF...</h3><p>Please wait while we prepare your invoice.</p>';
    document.body.appendChild(loadingMsg);

    // Direct PDF download - opens print dialog automatically
    // User selects "Save as PDF" from print dialog
    const pdfWindow = window.open(`../controllers/pdf_controller.php?project_id=${currentProjectId}`, '_blank');
    
    // Remove loading message after a delay
    setTimeout(() => {
        if (loadingMsg.parentNode) {
            loadingMsg.parentNode.removeChild(loadingMsg);
        }
    }, 2000);
}

// Profile Menu Functions
function toggleProfileMenu(event) {
    if (event) {
        event.stopPropagation();
    }
    const menu = document.getElementById('profileMenu');
    if (menu) {
        menu.classList.toggle('active');
    }
}

// Close profile menu when clicking outside
document.addEventListener('click', function(event) {
    const profileBtn = document.querySelector('.user-profile-btn');
    const profileMenu = document.getElementById('profileMenu');
    
    if (profileBtn && profileMenu) {
        // Check if click is outside both button and menu
        if (!profileBtn.contains(event.target) && !profileMenu.contains(event.target)) {
            profileMenu.classList.remove('active');
        }
    }
});

// Make toggleProfileMenu globally available
window.toggleProfileMenu = toggleProfileMenu;

// Profile Modal Functions
function openProfileModal() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'block';
    
    // Load current user data
    loadUserProfile();
    
    // Close profile menu
    document.getElementById('profileMenu').classList.remove('active');
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'none';
}

async function loadUserProfile() {
    try {
        const response = await fetch('../controllers/service_controller.php?action=get_user_profile');
        const result = await response.json();
        if (result.success && result.user) {
            document.getElementById('profile_mobile').value = result.user.mobile_number || '';
        }
    } catch (error) {
        console.error('Error loading user profile:', error);
    }
}

// Profile Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const mobile_number = document.getElementById('profile_mobile').value;
            const current_password = document.getElementById('profile_current_password').value;
            const new_password = document.getElementById('profile_new_password').value;
            const confirm_password = document.getElementById('profile_confirm_password').value;
            
            if (new_password && new_password !== confirm_password) {
                alert('New passwords do not match!');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_profile');
                formData.append('mobile_number', mobile_number);
                formData.append('current_password', current_password);
                formData.append('new_password', new_password);
                
                const response = await fetch('../controllers/profile_controller.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('Profile updated successfully!');
                    closeProfileModal();
                    // Clear password fields
                    document.getElementById('profile_current_password').value = '';
                    document.getElementById('profile_new_password').value = '';
                    document.getElementById('profile_confirm_password').value = '';
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('profileModal');
        if (event.target == modal) {
            closeProfileModal();
        }
    }
});

