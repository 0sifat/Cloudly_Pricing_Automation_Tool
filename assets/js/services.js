// Service-specific JavaScript functions

// EC2 Service
async function loadEC2Form(container) {
    container.innerHTML = `
        <h2>EC2 Instances</h2>
        <div class="service-form-content">
            <div id="ec2InstancesList">
                <div style="text-align: center; padding: 40px; color: #ccc;">
                    <p style="font-size: 16px; margin-bottom: 10px;">Loading...</p>
                </div>
            </div>
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button class="btn-add" onclick="addEC2Instance()">+ Add Instance</button>
                <button class="btn-clear" onclick="clearEC2Instances()">Clear All</button>
            </div>
        </div>
    `;
    await loadEC2Instances();
}

let ec2Instances = [];

function getProjectRegion() {
    const projectRegion = document.getElementById('project_region');
    if (projectRegion) {
        return projectRegion.value || '';
    }
    return '';
}

// Function to generate full region list HTML
function getRegionSelectHTML(selectedRegion = '', selectId = '') {
    const regions = [
        { value: 'us-east-1', label: 'US East (N. Virginia)' },
        { value: 'us-east-2', label: 'US East (Ohio)' },
        { value: 'us-west-1', label: 'US West (N. California)' },
        { value: 'us-west-2', label: 'US West (Oregon)' },
        { value: 'af-south-1', label: 'Africa (Cape Town)' },
        { value: 'ap-east-1', label: 'Asia Pacific (Hong Kong)' },
        { value: 'ap-south-2', label: 'Asia Pacific (Hyderabad)' },
        { value: 'ap-southeast-3', label: 'Asia Pacific (Jakarta)' },
        { value: 'ap-southeast-5', label: 'Asia Pacific (Malaysia)' },
        { value: 'ap-southeast-4', label: 'Asia Pacific (Melbourne)' },
        { value: 'ap-south-1', label: 'Asia Pacific (Mumbai)' },
        { value: 'ap-southeast-6', label: 'Asia Pacific (New Zealand)' },
        { value: 'ap-northeast-3', label: 'Asia Pacific (Osaka)' },
        { value: 'ap-northeast-2', label: 'Asia Pacific (Seoul)' },
        { value: 'ap-southeast-1', label: 'Asia Pacific (Singapore)' },
        { value: 'ap-southeast-2', label: 'Asia Pacific (Sydney)' },
        { value: 'ap-east-2', label: 'Asia Pacific (Taipei)' },
        { value: 'ap-southeast-7', label: 'Asia Pacific (Thailand)' },
        { value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo)' },
        { value: 'ca-central-1', label: 'Canada (Central)' },
        { value: 'ca-west-1', label: 'Canada West (Calgary)' },
        { value: 'eu-central-1', label: 'Europe (Frankfurt)' },
        { value: 'eu-west-1', label: 'Europe (Ireland)' },
        { value: 'eu-west-2', label: 'Europe (London)' },
        { value: 'eu-south-1', label: 'Europe (Milan)' },
        { value: 'eu-west-3', label: 'Europe (Paris)' },
        { value: 'eu-south-2', label: 'Europe (Spain)' },
        { value: 'eu-north-1', label: 'Europe (Stockholm)' },
        { value: 'eu-central-2', label: 'Europe (Zurich)' },
        { value: 'il-central-1', label: 'Israel (Tel Aviv)' },
        { value: 'mx-central-1', label: 'Mexico (Central)' },
        { value: 'me-south-1', label: 'Middle East (Bahrain)' },
        { value: 'me-central-1', label: 'Middle East (UAE)' },
        { value: 'sa-east-1', label: 'South America (São Paulo)' }
    ];
    
    let html = `<select id="${selectId}" onchange="updateVPCField('region', this.value)">`;
    html += `<option value="">Select Region</option>`;
    regions.forEach(region => {
        const selected = region.value === selectedRegion ? 'selected' : '';
        html += `<option value="${region.value}" ${selected}>${region.label}</option>`;
    });
    html += `</select>`;
    return html;
}

function getEKSRegionSelectHTML(selectedRegion = '') {
    const regions = [
        { value: 'us-east-1', label: 'US East (N. Virginia)' },
        { value: 'us-east-2', label: 'US East (Ohio)' },
        { value: 'us-west-1', label: 'US West (N. California)' },
        { value: 'us-west-2', label: 'US West (Oregon)' },
        { value: 'af-south-1', label: 'Africa (Cape Town)' },
        { value: 'ap-east-1', label: 'Asia Pacific (Hong Kong)' },
        { value: 'ap-south-2', label: 'Asia Pacific (Hyderabad)' },
        { value: 'ap-southeast-3', label: 'Asia Pacific (Jakarta)' },
        { value: 'ap-southeast-5', label: 'Asia Pacific (Malaysia)' },
        { value: 'ap-southeast-4', label: 'Asia Pacific (Melbourne)' },
        { value: 'ap-south-1', label: 'Asia Pacific (Mumbai)' },
        { value: 'ap-southeast-6', label: 'Asia Pacific (New Zealand)' },
        { value: 'ap-northeast-3', label: 'Asia Pacific (Osaka)' },
        { value: 'ap-northeast-2', label: 'Asia Pacific (Seoul)' },
        { value: 'ap-southeast-1', label: 'Asia Pacific (Singapore)' },
        { value: 'ap-southeast-2', label: 'Asia Pacific (Sydney)' },
        { value: 'ap-east-2', label: 'Asia Pacific (Taipei)' },
        { value: 'ap-southeast-7', label: 'Asia Pacific (Thailand)' },
        { value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo)' },
        { value: 'ca-central-1', label: 'Canada (Central)' },
        { value: 'ca-west-1', label: 'Canada West (Calgary)' },
        { value: 'eu-central-1', label: 'Europe (Frankfurt)' },
        { value: 'eu-west-1', label: 'Europe (Ireland)' },
        { value: 'eu-west-2', label: 'Europe (London)' },
        { value: 'eu-south-1', label: 'Europe (Milan)' },
        { value: 'eu-west-3', label: 'Europe (Paris)' },
        { value: 'eu-south-2', label: 'Europe (Spain)' },
        { value: 'eu-north-1', label: 'Europe (Stockholm)' },
        { value: 'eu-central-2', label: 'Europe (Zurich)' },
        { value: 'il-central-1', label: 'Israel (Tel Aviv)' },
        { value: 'mx-central-1', label: 'Mexico (Central)' },
        { value: 'me-south-1', label: 'Middle East (Bahrain)' },
        { value: 'me-central-1', label: 'Middle East (UAE)' },
        { value: 'sa-east-1', label: 'South America (São Paulo)' }
    ];
    
    let html = `<select id="eks_region" onchange="updateEKSField('region', this.value)">`;
    html += `<option value="">Select Region</option>`;
    regions.forEach(region => {
        const selected = region.value === selectedRegion ? 'selected' : '';
        html += `<option value="${region.value}" ${selected}>${region.label}</option>`;
    });
    html += `</select>`;
    return html;
}

function getLBRegionSelectHTML(selectedRegion = '') {
    const regions = [
        { value: 'us-east-1', label: 'US East (N. Virginia)' },
        { value: 'us-east-2', label: 'US East (Ohio)' },
        { value: 'us-west-1', label: 'US West (N. California)' },
        { value: 'us-west-2', label: 'US West (Oregon)' },
        { value: 'af-south-1', label: 'Africa (Cape Town)' },
        { value: 'ap-east-1', label: 'Asia Pacific (Hong Kong)' },
        { value: 'ap-south-2', label: 'Asia Pacific (Hyderabad)' },
        { value: 'ap-southeast-3', label: 'Asia Pacific (Jakarta)' },
        { value: 'ap-southeast-5', label: 'Asia Pacific (Malaysia)' },
        { value: 'ap-southeast-4', label: 'Asia Pacific (Melbourne)' },
        { value: 'ap-south-1', label: 'Asia Pacific (Mumbai)' },
        { value: 'ap-southeast-6', label: 'Asia Pacific (New Zealand)' },
        { value: 'ap-northeast-3', label: 'Asia Pacific (Osaka)' },
        { value: 'ap-northeast-2', label: 'Asia Pacific (Seoul)' },
        { value: 'ap-southeast-1', label: 'Asia Pacific (Singapore)' },
        { value: 'ap-southeast-2', label: 'Asia Pacific (Sydney)' },
        { value: 'ap-east-2', label: 'Asia Pacific (Taipei)' },
        { value: 'ap-southeast-7', label: 'Asia Pacific (Thailand)' },
        { value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo)' },
        { value: 'ca-central-1', label: 'Canada (Central)' },
        { value: 'ca-west-1', label: 'Canada West (Calgary)' },
        { value: 'eu-central-1', label: 'Europe (Frankfurt)' },
        { value: 'eu-west-1', label: 'Europe (Ireland)' },
        { value: 'eu-west-2', label: 'Europe (London)' },
        { value: 'eu-south-1', label: 'Europe (Milan)' },
        { value: 'eu-west-3', label: 'Europe (Paris)' },
        { value: 'eu-south-2', label: 'Europe (Spain)' },
        { value: 'eu-north-1', label: 'Europe (Stockholm)' },
        { value: 'eu-central-2', label: 'Europe (Zurich)' },
        { value: 'il-central-1', label: 'Israel (Tel Aviv)' },
        { value: 'mx-central-1', label: 'Mexico (Central)' },
        { value: 'me-south-1', label: 'Middle East (Bahrain)' },
        { value: 'me-central-1', label: 'Middle East (UAE)' },
        { value: 'sa-east-1', label: 'South America (São Paulo)' }
    ];
    
    let html = `<select id="lb_region" onchange="updateLBField('region', this.value)">`;
    html += `<option value="">Select Region</option>`;
    regions.forEach(region => {
        const selected = region.value === selectedRegion ? 'selected' : '';
        html += `<option value="${region.value}" ${selected}>${region.label}</option>`;
    });
    html += `</select>`;
    return html;
}

async function addEC2Instance() {
    const region = getProjectRegion();
    if (!region) {
        alert('Please select a region for the project first.');
        return;
    }
    
    const instance = {
        id: Date.now(),
        instance_type: '',
        quantity: 1,
        operating_system: 'Linux',
        region: region,
        vcpu: 0,
        memory_gb: 0,
        pricing_models: [],
        display_name: ''
    };
    ec2Instances.push(instance);
    window.ec2Instances = ec2Instances;
    await renderEC2Instances();
    // Refresh EBS volumes if visible
    setTimeout(() => {
        if (document.getElementById('ebsVolumesList')) {
            renderEBSVolumes();
        }
    }, 100);
}

function removeEC2Instance(id) {
    ec2Instances = ec2Instances.filter(inst => inst.id !== id);
    window.ec2Instances = ec2Instances;
    renderEC2Instances();
}

async function clearEC2Instances() {
    if (confirm('Are you sure you want to clear all EC2 instances?')) {
        ec2Instances = [];
        window.ec2Instances = ec2Instances;
        await renderEC2Instances();
    }
}

function updateEC2Quantity(id, delta) {
    const instance = ec2Instances.find(inst => inst.id === id);
    if (instance) {
        instance.quantity = Math.max(1, instance.quantity + delta);
        window.ec2Instances = ec2Instances;
        renderEC2Instances();
    }
}

let ec2InstanceOptions = {}; // Cache for instance options by region

async function fetchEC2InstancesByRegion(region) {
    if (!region) {
        return [];
    }
    // Clear cache to force refresh
    delete ec2InstanceOptions[region];
    try {
        const url = `../controllers/service_controller.php?action=get_ec2_instances_by_region&region=${encodeURIComponent(region)}`;
        const response = await fetch(url);
        const result = await response.json();
        if (result.success && result.instances) {
            ec2InstanceOptions[region] = result.instances;
            return result.instances;
        }
    } catch (error) {
        console.error('Error fetching EC2 instances:', error);
    }
    return [];
}

async function renderEC2Instances() {
    const container = document.getElementById('ec2InstancesList');
    if (!container) {
        console.error('EC2 instances container not found');
        return;
    }

    if (ec2Instances.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ccc;"><p style="font-size: 16px; margin-bottom: 10px;">No instances added yet.</p><p style="font-size: 14px; color: #999;">Click "Add Instance" to get started.</p></div>';
        return;
    }

    try {
        const region = getProjectRegion();
        let availableInstances = [];
        
        if (region) {
            availableInstances = await fetchEC2InstancesByRegion(region);
        } else {
            console.warn('No region selected, showing empty instance list');
        }

        const instanceOptions = availableInstances.map(opt => {
            const vcpu = opt.vcpu || opt.VCPU || 0;
            const memory = opt.memory_gb || opt.MEMORY_GB || 0;
            return `<option value="${opt.instance_type}" data-vcpu="${vcpu}" data-memory="${memory}">${opt.instance_type} (${vcpu} vCPU / ${memory} GB RAM)</option>`;
        }).join('');

        container.innerHTML = ec2Instances.map(inst => {
            const selectedOption = inst.instance_type && !availableInstances.find(opt => opt.instance_type === inst.instance_type) 
                ? `<option value="${inst.instance_type}" selected>${inst.instance_type} (Custom)</option>` 
                : '';
            
            // Set selected value if instance type exists in available instances
            const selectedValue = inst.instance_type && availableInstances.find(opt => opt.instance_type === inst.instance_type) 
                ? inst.instance_type 
                : '';
            
            return `
        <div class="instance-item">
            <div class="instance-header">
                <h4>Instance ${ec2Instances.indexOf(inst) + 1}</h4>
                <button class="btn-remove" onclick="removeEC2Instance(${inst.id})">Remove</button>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>Instance Type</label>
                    <select id="ec2_instance_type_${inst.id}" onchange="updateEC2InstanceType(${inst.id}, this.value, this.options[this.selectedIndex].dataset.vcpu, this.options[this.selectedIndex].dataset.memory)">
                        <option value="">Select Instance Type</option>
                        ${instanceOptions}
                        ${selectedOption}
                    </select>
                </div>
                <div class="form-field">
                    <label>vCPU</label>
                    <input type="number" min="0" step="1" value="${inst.vcpu || 0}" onchange="updateEC2Field(${inst.id}, 'vcpu', parseInt(this.value))" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                </div>
                <div class="form-field">
                    <label>Memory (GB)</label>
                    <input type="number" min="0" step="0.01" value="${inst.memory_gb || 0}" onchange="updateEC2Field(${inst.id}, 'memory_gb', parseFloat(this.value))" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                </div>
                <div class="form-field">
                    <label>Operating System</label>
                    <select onchange="updateEC2Field(${inst.id}, 'operating_system', this.value)">
                        <option value="Linux" ${inst.operating_system === 'Linux' ? 'selected' : ''}>Linux</option>
                        <option value="Windows" ${inst.operating_system === 'Windows' ? 'selected' : ''}>Windows</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Quantity</label>
                    <div class="quantity-controls">
                        <button class="qty-minus" onclick="updateEC2Quantity(${inst.id}, -1)">-</button>
                        <span>${inst.quantity}</span>
                        <button class="qty-plus" onclick="updateEC2Quantity(${inst.id}, 1)">+</button>
                    </div>
                </div>
            </div>
            <div class="pricing-models-section">
                <h4 style="color: #fff; margin-bottom: 20px; font-size: 18px; font-weight: 600;">Select the container and options to find your best price</h4>
                <div class="pricing-models-grid">
                    <div class="pricing-model-card" style="background: ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan') ? '#2a4a2a' : '#1a1a1a'}; border: 2px solid ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}; border-radius: 10px; padding: 20px; cursor: pointer;" onclick="togglePricingModelCard(${inst.id}, 'compute_savings_plan')" onmouseover="this.style.borderColor='rgba(255, 107, 53, 0.6)'" onmouseout="this.style.borderColor='${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}'">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                            <input type="radio" name="pricing_model_${inst.id}" ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan') ? 'checked' : ''} onchange="togglePricingModel(${inst.id}, 'compute_savings_plan', this.checked)" onclick="event.stopPropagation()" style="width: 20px; height: 20px; cursor: pointer;">
                            <strong style="color: #fff; font-size: 16px; font-weight: 600;">Compute Savings Plans</strong>
                        </div>
                        <p style="color: #ccc; font-size: 14px; margin-bottom: 15px; line-height: 1.6;">One plan that automatically applies to all usage on EC2, Fargate, and Lambda. Up to 66% discount.</p>
                            <div id="compute_savings_plan_${inst.id}" style="display: ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan') ? 'block' : 'none'}; margin-top: 10px;">
                            <div class="form-field" style="margin-bottom: 15px;">
                                <label style="color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Reservation Term</label>
                                <select onchange="updatePricingModel(${inst.id}, 'compute_savings_plan', 'reservation_term', this.value)" style="width: 100%; padding: 12px 15px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px; font-size: 14px; font-weight: 500; min-height: 45px;">
                                    <option value="1" ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan')?.reservation_term == 1 ? 'selected' : ''}>1 Year</option>
                                    <option value="3" ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan')?.reservation_term == 3 ? 'selected' : ''}>3 Years</option>
                                </select>
                            </div>
                            <div class="form-field" style="margin-bottom: 15px;">
                                <label style="color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Payment Option</label>
                                <select onchange="updatePricingModel(${inst.id}, 'compute_savings_plan', 'payment_option', this.value)" style="width: 100%; padding: 12px 15px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px; font-size: 14px; font-weight: 500; min-height: 45px;">
                                    <option value="no_upfront" ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan')?.payment_option === 'no_upfront' ? 'selected' : ''}>No Upfront</option>
                                    <option value="partial_upfront" ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan')?.payment_option === 'partial_upfront' ? 'selected' : ''}>Partial Upfront</option>
                                    <option value="all_upfront" ${inst.pricing_models.find(pm => pm.model === 'compute_savings_plan')?.payment_option === 'all_upfront' ? 'selected' : ''}>All Upfront</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pricing-model-card" style="background: ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan') ? '#2a4a2a' : '#1a1a1a'}; border: 2px solid ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}; border-radius: 10px; padding: 20px; cursor: pointer;" onclick="togglePricingModelCard(${inst.id}, 'ec2_savings_plan')" onmouseover="this.style.borderColor='rgba(255, 107, 53, 0.6)'" onmouseout="this.style.borderColor='${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}'">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                            <input type="radio" name="pricing_model_${inst.id}" ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan') ? 'checked' : ''} onchange="togglePricingModel(${inst.id}, 'ec2_savings_plan', this.checked)" onclick="event.stopPropagation()" style="width: 20px; height: 20px; cursor: pointer;">
                            <strong style="color: #fff; font-size: 16px; font-weight: 600;">EC2 Instance Savings Plans</strong>
                        </div>
                        <p style="color: #ccc; font-size: 14px; margin-bottom: 15px; line-height: 1.6;">Get deeper discount when you only need one instance family and region. Up to 72% discount.</p>
                            <div id="ec2_savings_plan_${inst.id}" style="display: ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan') ? 'block' : 'none'}; margin-top: 10px;">
                            <div class="form-field" style="margin-bottom: 15px;">
                                <label style="color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Reservation Term</label>
                                <select onchange="updatePricingModel(${inst.id}, 'ec2_savings_plan', 'reservation_term', this.value)" style="width: 100%; padding: 12px 15px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px; font-size: 14px; font-weight: 500; min-height: 45px;">
                                    <option value="1" ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan')?.reservation_term == 1 ? 'selected' : ''}>1 Year</option>
                                    <option value="3" ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan')?.reservation_term == 3 ? 'selected' : ''}>3 Years</option>
                                </select>
                            </div>
                            <div class="form-field" style="margin-bottom: 15px;">
                                <label style="color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Payment Option</label>
                                <select onchange="updatePricingModel(${inst.id}, 'ec2_savings_plan', 'payment_option', this.value)" style="width: 100%; padding: 12px 15px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px; font-size: 14px; font-weight: 500; min-height: 45px;">
                                    <option value="no_upfront" ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan')?.payment_option === 'no_upfront' ? 'selected' : ''}>No Upfront</option>
                                    <option value="partial_upfront" ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan')?.payment_option === 'partial_upfront' ? 'selected' : ''}>Partial Upfront</option>
                                    <option value="all_upfront" ${inst.pricing_models.find(pm => pm.model === 'ec2_savings_plan')?.payment_option === 'all_upfront' ? 'selected' : ''}>All Upfront</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pricing-model-card" style="background: ${inst.pricing_models.find(pm => pm.model === 'on_demand') ? '#2a4a2a' : '#1a1a1a'}; border: 2px solid ${inst.pricing_models.find(pm => pm.model === 'on_demand') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}; border-radius: 10px; padding: 20px; cursor: pointer;" onclick="togglePricingModelCard(${inst.id}, 'on_demand')" onmouseover="this.style.borderColor='rgba(255, 107, 53, 0.6)'" onmouseout="this.style.borderColor='${inst.pricing_models.find(pm => pm.model === 'on_demand') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}'">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                            <input type="radio" name="pricing_model_${inst.id}" ${inst.pricing_models.find(pm => pm.model === 'on_demand') ? 'checked' : ''} onchange="togglePricingModel(${inst.id}, 'on_demand', this.checked)" onclick="event.stopPropagation()" style="width: 20px; height: 20px; cursor: pointer;">
                            <strong style="color: #fff; font-size: 16px; font-weight: 600;">On-Demand</strong>
                        </div>
                        <p style="color: #ccc; font-size: 14px; margin-bottom: 15px; line-height: 1.6;">Maximize flexibility.</p>
                            <div id="on_demand_${inst.id}" style="display: ${inst.pricing_models.find(pm => pm.model === 'on_demand') ? 'block' : 'none'}; margin-top: 10px;">
                            <div class="form-field" style="margin-bottom: 15px;">
                                <label style="color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Expected Utilization (%)</label>
                                <input type="number" min="1" max="100" value="${inst.pricing_models.find(pm => pm.model === 'on_demand')?.utilization || '100'}" onchange="updatePricingModel(${inst.id}, 'on_demand', 'utilization', this.value)" style="width: 100%; padding: 12px 15px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px; font-size: 14px; font-weight: 500; min-height: 45px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="pricing-model-card" style="background: ${inst.pricing_models.find(pm => pm.model === 'spot') ? '#2a4a2a' : '#1a1a1a'}; border: 2px solid ${inst.pricing_models.find(pm => pm.model === 'spot') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}; border-radius: 10px; padding: 20px; cursor: pointer;" onclick="togglePricingModelCard(${inst.id}, 'spot')" onmouseover="this.style.borderColor='rgba(255, 107, 53, 0.6)'" onmouseout="this.style.borderColor='${inst.pricing_models.find(pm => pm.model === 'spot') ? '#ff6b35' : 'rgba(255, 107, 53, 0.3)'}'">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                            <input type="radio" name="pricing_model_${inst.id}" ${inst.pricing_models.find(pm => pm.model === 'spot') ? 'checked' : ''} onchange="togglePricingModel(${inst.id}, 'spot', this.checked)" onclick="event.stopPropagation()" style="width: 20px; height: 20px; cursor: pointer;">
                            <strong style="color: #fff; font-size: 16px; font-weight: 600;">Spot Instances</strong>
                        </div>
                        <p style="color: #ccc; font-size: 14px; margin-bottom: 15px; line-height: 1.6;">Minimize cost by leveraging EC2's spare capacity. Recommended for fault tolerant applications.</p>
                            <div id="spot_${inst.id}" style="display: ${inst.pricing_models.find(pm => pm.model === 'spot') ? 'block' : 'none'}; margin-top: 10px;">
                            <div class="form-field" style="margin-bottom: 15px;">
                                <label style="color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Assume percentage discount for my estimate (%)</label>
                                <input type="number" min="0" max="100" value="${inst.pricing_models.find(pm => pm.model === 'spot')?.spot_discount || '70'}" onchange="updatePricingModel(${inst.id}, 'spot', 'spot_discount', this.value)" style="width: 100%; padding: 12px 15px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px; font-size: 14px; font-weight: 500; min-height: 45px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
        }).join('');
        
        // Set selected values for instance type dropdowns after rendering
        setTimeout(() => {
            ec2Instances.forEach((inst) => {
                const select = document.getElementById(`ec2_instance_type_${inst.id}`);
                if (select && inst.instance_type) {
                    select.value = inst.instance_type;
                    // Also update vCPU and memory if instance type is selected
                    const selectedOption = select.options[select.selectedIndex];
                    if (selectedOption && selectedOption.dataset.vcpu) {
                        const vcpuInput = container.querySelector(`input[onchange*="updateEC2Field(${inst.id}, 'vcpu'"]`);
                        const memoryInput = container.querySelector(`input[onchange*="updateEC2Field(${inst.id}, 'memory_gb'"]`);
                        if (vcpuInput && selectedOption.dataset.vcpu) {
                            vcpuInput.value = selectedOption.dataset.vcpu;
                            inst.vcpu = parseInt(selectedOption.dataset.vcpu) || 0;
                        }
                        if (memoryInput && selectedOption.dataset.memory) {
                            memoryInput.value = selectedOption.dataset.memory;
                            inst.memory_gb = parseFloat(selectedOption.dataset.memory) || 0;
                        }
                    }
                }
            });
        }, 100);
    } catch (error) {
        console.error('Error rendering EC2 instances:', error);
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><p style="font-size: 16px; margin-bottom: 10px;">Error loading instances.</p><p style="font-size: 14px; color: #999;">Please try again.</p></div>';
    }
}

async function updateEC2Field(id, field, value) {
    const instance = ec2Instances.find(inst => inst.id === id);
    if (instance) {
        instance[field] = value;
        // Update display name if instance type or quantity changes
        if (field === 'instance_type' || field === 'quantity') {
            instance.display_name = `${instance.instance_type} (x${instance.quantity}) - ${instance.operating_system}`;
        }
        window.ec2Instances = ec2Instances;
        saveEC2Instances();
        // Refresh EBS volumes if visible
        if (document.getElementById('ebsVolumesList')) {
            renderEBSVolumes();
        }
    }
}

async function updateEC2InstanceType(id, instanceType, vcpu, memoryGb) {
    const instance = ec2Instances.find(inst => inst.id === id);
    if (instance) {
        instance.instance_type = instanceType;
        instance.vcpu = parseInt(vcpu) || 0;
        instance.memory_gb = parseFloat(memoryGb) || 0;
        instance.display_name = `${instance.instance_type} (x${instance.quantity}) - ${instance.operating_system}`;
        window.ec2Instances = ec2Instances;
        
        // Update the input fields immediately before re-rendering
        const vcpuInput = document.querySelector(`input[onchange*="updateEC2Field(${id}, 'vcpu'"]`);
        const memoryInput = document.querySelector(`input[onchange*="updateEC2Field(${id}, 'memory_gb'"]`);
        if (vcpuInput) {
            vcpuInput.value = instance.vcpu;
        }
        if (memoryInput) {
            memoryInput.value = instance.memory_gb;
        }
        
        await renderEC2Instances();
        saveEC2Instances();
        // Refresh EBS volumes if visible
        if (document.getElementById('ebsVolumesList')) {
            renderEBSVolumes();
        }
    }
}

async function togglePricingModel(instanceId, model, enabled) {
    const instance = ec2Instances.find(inst => inst.id === instanceId);
    if (!instance) return;

    if (enabled) {
        // Clear all other pricing models first (radio button behavior - only one at a time)
        instance.pricing_models = [];
        const newModel = {
            model: model,
            reservation_term: model.includes('savings') ? 3 : 0,
            payment_option: model.includes('savings') ? 'no_upfront' : '',
            utilization: model === 'on_demand' ? '100' : '',
            spot_discount: model === 'spot' ? '70' : ''
        };
        instance.pricing_models.push(newModel);
    } else {
        instance.pricing_models = instance.pricing_models.filter(pm => pm.model !== model);
    }
    window.ec2Instances = ec2Instances;
    await renderEC2Instances();
    saveEC2Instances();
}

function togglePricingModelCard(instanceId, model) {
    const instance = ec2Instances.find(inst => inst.id === instanceId);
    if (!instance) return;
    
    const isEnabled = instance.pricing_models.find(pm => pm.model === model);
    const detailsDiv = document.getElementById(`${model}_${instanceId}`);
    
    if (detailsDiv) {
        if (isEnabled) {
            detailsDiv.style.display = detailsDiv.style.display === 'none' ? 'block' : 'none';
        }
    }
}

// renderPricingDetails function removed - now handled inline in renderEC2Instances

async function updatePricingModel(instanceId, model, field, value) {
    const instance = ec2Instances.find(inst => inst.id === instanceId);
    if (!instance) return;

    const pricingModel = instance.pricing_models.find(pm => pm.model === model);
    if (pricingModel) {
        pricingModel[field] = value;
        window.ec2Instances = ec2Instances;
        saveEC2Instances();
        // Re-render to update display
        await renderEC2Instances();
    }
}

async function saveEC2Instances() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;

    try {
        const formData = new FormData();
        formData.append('action', 'save_ec2');
        formData.append('project_id', projectId);
        formData.append('instances', JSON.stringify(ec2Instances));

        const response = await fetch('../controllers/service_controller.php', {
            method: 'POST',
            body: formData
        });
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Non-JSON response:', responseText);
            alert('Error: Server returned non-JSON response. Please check console for details.');
            return;
        }
        
        // Try to parse JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parsing JSON response:', parseError);
            console.error('Raw server response:', responseText);
            alert('Error: Invalid JSON response from server. Please check console for details.');
            return;
        }
        if (result.success) {
            // Reload instances to get database IDs
            await loadEC2Instances();
            // Refresh EBS volumes if they're visible
            if (document.getElementById('ebsVolumesList')) {
                renderEBSVolumes();
            }
        } else {
            alert('Error: ' + (result.error || 'Failed to save EC2 instances'));
        }
    } catch (error) {
        console.error('Error saving EC2 instances:', error);
        alert('Error saving EC2 instances. Please check console for details.');
    }
}

// Make ec2Instances globally available
window.ec2Instances = ec2Instances;

async function loadEC2Instances() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        // If no project ID, just show empty state
        ec2Instances = [];
        window.ec2Instances = ec2Instances;
        await renderEC2Instances();
        return;
    }

    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_ec2&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.instances) {
            ec2Instances = result.instances.map(inst => ({
                ...inst,
                id: inst.id || Date.now() + Math.random(),
                pricing_models: inst.pricing_models || []
            }));
            window.ec2Instances = ec2Instances;
            await renderEC2Instances();
        } else {
            ec2Instances = [];
            window.ec2Instances = ec2Instances;
            await renderEC2Instances();
        }
    } catch (error) {
        console.error('Error loading EC2 instances:', error);
        ec2Instances = [];
        window.ec2Instances = ec2Instances;
        await renderEC2Instances();
    }
}

// EBS Service
let ebsVolumes = [];

function loadEBSForm(container) {
    container.innerHTML = `
        <h2>EBS Volumes</h2>
        <div class="service-form-content">
            <div id="ebsVolumesList"></div>
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button class="btn-add" onclick="addEBSVolume()">+ Add Volume</button>
                <button class="btn-clear" onclick="clearEBSVolumes()">Clear All</button>
            </div>
        </div>
    `;
    loadEBSVolumes();
}

function addEBSVolume() {
    const projectRegion = getProjectRegion();
    const volume = {
        id: Date.now(),
        server_type: 'app_server',
        server_name: '',
        ec2_instance_id: null,
        volume_type: 'gp2',
        size_gb: 20,
        iops: 0,
        throughput: 0,
        snapshot_frequency: 'none',
        snapshot_storage_gb: 0,
        region: projectRegion // Set region from project
    };
    ebsVolumes.push(volume);
    renderEBSVolumes();
}

function removeEBSVolume(id) {
    ebsVolumes = ebsVolumes.filter(vol => vol.id !== id);
    renderEBSVolumes();
}

function clearEBSVolumes() {
    if (confirm('Are you sure you want to clear all EBS volumes?')) {
        ebsVolumes = [];
        renderEBSVolumes();
    }
}

// Check if volume type requires IOPS/Throughput fields
function ebsNeedsIopsThroughput(volumeType) {
    return ['gp3', 'io1', 'io2'].includes(volumeType);
}

async function renderEBSVolumes() {
    const container = document.getElementById('ebsVolumesList');
    if (!container) return;

    // Get EC2 instances for dropdown - use both saved and current session instances
    const projectId = document.getElementById('project_id')?.value;
    let ec2Instances = [];
    
    // First, get from database
    if (projectId) {
        try {
            const response = await fetch(`../controllers/service_controller.php?action=get_ec2&project_id=${projectId}`);
            const result = await response.json();
            if (result.success && result.instances && result.instances.length > 0) {
                ec2Instances = result.instances;
            }
        } catch (error) {
            console.error('Error loading EC2 instances:', error);
        }
    }
    
    // Also include current session instances if available
    if (ec2Instances.length === 0 && window.ec2Instances && window.ec2Instances.length > 0) {
        ec2Instances = window.ec2Instances.map(inst => ({
            id: inst.id || `temp_${inst.instance_type}_${Date.now()}`,
            instance_type: inst.instance_type,
            quantity: inst.quantity
        }));
    }

    if (ebsVolumes.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ccc;"><p style="font-size: 16px; margin-bottom: 10px;">No volumes added yet.</p><p style="font-size: 14px; color: #999;">Click "Add Volume" to get started.</p></div>';
        return;
    }

    container.innerHTML = ebsVolumes.map(vol => {
        const showIopsThroughput = ebsNeedsIopsThroughput(vol.volume_type);
        const showSnapshotStorage = vol.snapshot_frequency !== 'none';
        
        return `
        <div class="instance-item">
            <div class="instance-header">
                <h4>Volume ${ebsVolumes.indexOf(vol) + 1}</h4>
                <button class="btn-remove" onclick="removeEBSVolume(${vol.id})">Remove</button>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>Server Type</label>
                    <select onchange="updateEBSField(${vol.id}, 'server_type', this.value)">
                        <option value="app_server" ${vol.server_type === 'app_server' ? 'selected' : ''}>App Server</option>
                        <option value="db_server" ${vol.server_type === 'db_server' ? 'selected' : ''}>DB Server</option>
                        <option value="web_server" ${vol.server_type === 'web_server' ? 'selected' : ''}>Web Server</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Server Name</label>
                    <input type="text" value="${vol.server_name || ''}" onchange="updateEBSField(${vol.id}, 'server_name', this.value)" placeholder="e.g., Production DB">
                </div>
                <div class="form-field">
                    <label>Associated EC2 Instance</label>
                    <select onchange="updateEBSField(${vol.id}, 'ec2_instance_id', this.value)">
                        <option value="">None</option>
                        ${ec2Instances.length > 0 ? ec2Instances.map(inst => {
                            const displayName = inst.display_name || `${inst.instance_type} (x${inst.quantity}) - ${inst.operating_system || 'Linux'}`;
                            return `<option value="${inst.id}" ${vol.ec2_instance_id == inst.id ? 'selected' : ''}>${displayName}</option>`;
                        }).join('') : '<option disabled style="color: #ff6b35;">⚠️ No EC2 instances found. Please add EC2 instances first.</option>'}
                    </select>
                </div>
                <div class="form-field">
                    <label>Volume Type</label>
                    <select onchange="updateEBSVolumeType(${vol.id}, this.value)">
                        <option value="gp2" ${vol.volume_type === 'gp2' ? 'selected' : ''}>gp2 - General Purpose SSD</option>
                        <option value="gp3" ${vol.volume_type === 'gp3' ? 'selected' : ''}>gp3 - General Purpose SSD</option>
                        <option value="io1" ${vol.volume_type === 'io1' ? 'selected' : ''}>io1 - Provisioned IOPS SSD</option>
                        <option value="io2" ${vol.volume_type === 'io2' ? 'selected' : ''}>io2 - Provisioned IOPS SSD</option>
                        <option value="st1" ${vol.volume_type === 'st1' ? 'selected' : ''}>st1 - Throughput Optimized HDD</option>
                        <option value="sc1" ${vol.volume_type === 'sc1' ? 'selected' : ''}>sc1 - Cold HDD</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Size (GB)</label>
                    <input type="number" min="1" value="${vol.size_gb}" onchange="updateEBSField(${vol.id}, 'size_gb', parseInt(this.value))">
                </div>
            </div>
            ${showIopsThroughput ? `
            <div class="form-row" style="margin-top: 15px;">
                <div class="form-field">
                    <label>IOPS</label>
                    <input type="number" min="0" value="${vol.iops || 0}" onchange="updateEBSField(${vol.id}, 'iops', parseInt(this.value))" placeholder="e.g., 3000">
                </div>
                ${vol.volume_type === 'gp3' ? `
                <div class="form-field">
                    <label>Throughput (MB/s)</label>
                    <input type="number" min="0" value="${vol.throughput || 0}" onchange="updateEBSField(${vol.id}, 'throughput', parseInt(this.value))" placeholder="e.g., 125">
                </div>
                ` : ''}
            </div>
            ` : ''}
            <div class="form-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 107, 53, 0.2);">
                <div class="form-field">
                    <label>Snapshot Frequency</label>
                    <select onchange="updateEBSSnapshotFrequency(${vol.id}, this.value)">
                        <option value="none" ${vol.snapshot_frequency === 'none' ? 'selected' : ''}>No Snapshot</option>
                        <option value="hourly" ${vol.snapshot_frequency === 'hourly' ? 'selected' : ''}>Hourly</option>
                        <option value="daily" ${vol.snapshot_frequency === 'daily' ? 'selected' : ''}>Daily</option>
                        <option value="2x_daily" ${vol.snapshot_frequency === '2x_daily' ? 'selected' : ''}>2x Daily</option>
                        <option value="3x_daily" ${vol.snapshot_frequency === '3x_daily' ? 'selected' : ''}>3x Daily</option>
                        <option value="4x_daily" ${vol.snapshot_frequency === '4x_daily' ? 'selected' : ''}>4x Daily</option>
                        <option value="6x_daily" ${vol.snapshot_frequency === '6x_daily' ? 'selected' : ''}>6x Daily</option>
                        <option value="weekly" ${vol.snapshot_frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
                        <option value="monthly" ${vol.snapshot_frequency === 'monthly' ? 'selected' : ''}>Monthly</option>
                    </select>
                </div>
                ${showSnapshotStorage ? `
                <div class="form-field">
                    <label>Amount Changed Per Snapshot (GB)</label>
                    <input type="number" min="0" value="${vol.snapshot_storage_gb || 0}" onchange="updateEBSField(${vol.id}, 'snapshot_storage_gb', parseInt(this.value))" placeholder="e.g., 10">
                </div>
                ` : ''}
            </div>
        </div>
    `;
    }).join('');
}

// Handle volume type change - reset IOPS/throughput for gp2
function updateEBSVolumeType(id, volumeType) {
    const volume = ebsVolumes.find(vol => vol.id === id);
    if (volume) {
        volume.volume_type = volumeType;
        // Reset IOPS and throughput for gp2, st1, sc1
        if (!ebsNeedsIopsThroughput(volumeType)) {
            volume.iops = 0;
            volume.throughput = 0;
        }
        renderEBSVolumes();
        saveEBSVolumes();
    }
}

// Handle snapshot frequency change
function updateEBSSnapshotFrequency(id, frequency) {
    const volume = ebsVolumes.find(vol => vol.id === id);
    if (volume) {
        volume.snapshot_frequency = frequency;
        // Reset snapshot storage if no snapshot needed
        if (frequency === 'none') {
            volume.snapshot_storage_gb = 0;
        }
        renderEBSVolumes();
        saveEBSVolumes();
    }
}

function updateEBSField(id, field, value) {
    const volume = ebsVolumes.find(vol => vol.id === id);
    if (volume) {
        volume[field] = value;
        saveEBSVolumes();
    }
}

async function saveEBSVolumes() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;

    try {
        const formData = new FormData();
        formData.append('action', 'save_ebs');
        formData.append('project_id', projectId);
        
        // Ensure all volumes have region set
        const projectRegion = getProjectRegion();
        const volumesWithRegion = ebsVolumes.map(vol => ({
            ...vol,
            region: vol.region || projectRegion
        }));
        
        formData.append('volumes', JSON.stringify(volumesWithRegion));

        const response = await fetch('../controllers/service_controller.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            // Reload volumes to get updated costs
            await loadEBSVolumes();
        } else {
            console.error('Error saving EBS volumes:', result.error);
            alert('Error saving EBS volumes: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving EBS volumes:', error);
        alert('Error saving EBS volumes. Please check console for details.');
    }
}

async function loadEBSVolumes() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;

    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_ebs&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.volumes) {
            const projectRegion = getProjectRegion();
            // Ensure all volumes have region set
            ebsVolumes = result.volumes.map(vol => ({
                ...vol,
                region: vol.region || projectRegion
            }));
            renderEBSVolumes();
        }
    } catch (error) {
        console.error('Error loading EBS volumes:', error);
    }
}

// VPC Service
let vpcConfig = {
    region: '',
    vpc_count: 1,
    availability_zones: 2,
    nat_gateway_count: 0,
    vpc_endpoint_count: 0,
    data_transfer_gb: 0
};

function loadVPCForm(container) {
    container.innerHTML = `
        <h2>VPC Configuration</h2>
        <div class="service-form-content">
            <form id="vpcForm">
            <div class="form-row">
                <div class="form-field">
                    <label>Region</label>
                    ${getRegionSelectHTML(vpcConfig.region || getProjectRegion(), 'vpc_region')}
                </div>
                <div class="form-field">
                    <label>VPC Count</label>
                    <input type="number" min="1" id="vpc_count" value="${vpcConfig.vpc_count}" onchange="updateVPCField('vpc_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Availability Zones</label>
                    <input type="number" min="1" max="6" id="vpc_az" value="${vpcConfig.availability_zones}" onchange="updateVPCField('availability_zones', parseInt(this.value))">
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>NAT Gateway Count</label>
                    <input type="number" min="0" id="vpc_nat" value="${vpcConfig.nat_gateway_count}" onchange="updateVPCField('nat_gateway_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>VPC Endpoint Count</label>
                    <input type="number" min="0" id="vpc_endpoint" value="${vpcConfig.vpc_endpoint_count}" onchange="updateVPCField('vpc_endpoint_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Data Transfer (GB)</label>
                    <input type="number" min="0" id="vpc_transfer" value="${vpcConfig.data_transfer_gb}" onchange="updateVPCField('data_transfer_gb', parseInt(this.value))">
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveVPCConfig()">Save VPC Configuration</button>
            </form>
        </div>
    `;
    loadVPCConfig();
}

function updateVPCField(field, value) {
    vpcConfig[field] = value;
}

async function saveVPCConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'save_vpc');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(vpcConfig));

        const response = await fetch('../controllers/service_controller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            alert('VPC configuration saved successfully!');
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error saving VPC config:', error);
        alert('An error occurred. Please try again.');
    }
}

async function loadVPCConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;

    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_vpc&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            vpcConfig = result.config;
            // Update form fields
            if (document.getElementById('vpc_region')) document.getElementById('vpc_region').value = vpcConfig.region;
            if (document.getElementById('vpc_count')) document.getElementById('vpc_count').value = vpcConfig.vpc_count;
            if (document.getElementById('vpc_az')) document.getElementById('vpc_az').value = vpcConfig.availability_zones;
            if (document.getElementById('vpc_nat')) document.getElementById('vpc_nat').value = vpcConfig.nat_gateway_count;
            if (document.getElementById('vpc_endpoint')) document.getElementById('vpc_endpoint').value = vpcConfig.vpc_endpoint_count;
            if (document.getElementById('vpc_transfer')) document.getElementById('vpc_transfer').value = vpcConfig.data_transfer_gb;
        }
    } catch (error) {
        console.error('Error loading VPC config:', error);
    }
}

// RDS Service - Updated to support multiple instances
let rdsInstances = [];

async function loadRDSForm(container) {
    container.innerHTML = `
        <h2>RDS Instances</h2>
        <div class="service-form-content">
            <div id="rdsInstancesList">
                <div style="text-align: center; padding: 40px; color: #ccc;">
                    <p style="font-size: 16px; margin-bottom: 10px;">Loading...</p>
                </div>
            </div>
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button class="btn-add" onclick="addRDSInstance()">+ Add RDS Instance</button>
                <button class="btn-clear" onclick="clearRDSInstances()">Clear All</button>
            </div>
        </div>
    `;
    // Clear cache when loading form to ensure fresh data
    if (typeof rdsInstanceOptions !== 'undefined') {
        rdsInstanceOptions = {};
    }
    await loadRDSInstances();
}

let rdsInstanceOptions = {}; // Cache for RDS instance options by region and engine

async function fetchRDSInstancesByRegion(region, engine) {
    if (!region) {
        return [];
    }
    // Clear cache to force refresh
    const cacheKey = `${region}_${engine || 'all'}`;
    delete rdsInstanceOptions[cacheKey];
    try {
        const url = `../controllers/service_controller.php?action=get_rds_instances_by_region&region=${encodeURIComponent(region)}${engine ? '&engine=' + encodeURIComponent(engine) : ''}`;
        const response = await fetch(url);
        const result = await response.json();
        if (result.success && result.instances) {
            rdsInstanceOptions[cacheKey] = result.instances;
            return result.instances;
        }
    } catch (error) {
        console.error('Error fetching RDS instances:', error);
    }
    return [];
}

async function addRDSInstance() {
    const region = getProjectRegion();
    if (!region) {
        alert('Please select a region for the project first.');
        return;
    }
    
    const instance = {
        id: Date.now(),
        engine: 'mysql',
        instance_type: '',
        quantity: 1,
        storage_gb: 20,
        storage_type: 'gp2',
        multi_az: false,
        backup_retention: 7,
        region: region,
        vcpu: 0,
        memory_gb: 0
    };
    rdsInstances.push(instance);
    window.rdsInstances = rdsInstances;
    await renderRDSInstances();
}

async function removeRDSInstance(id) {
    rdsInstances = rdsInstances.filter(inst => inst.id !== id);
    window.rdsInstances = rdsInstances;
    await renderRDSInstances();
}

async function clearRDSInstances() {
    if (confirm('Are you sure you want to clear all RDS instances?')) {
        rdsInstances = [];
        window.rdsInstances = rdsInstances;
        await renderRDSInstances();
    }
}

async function renderRDSInstances() {
    const container = document.getElementById('rdsInstancesList');
    if (!container) {
        console.error('RDS instances container not found');
        return;
    }

    if (rdsInstances.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ccc;"><p style="font-size: 16px; margin-bottom: 10px;">No RDS instances added yet.</p><p style="font-size: 14px; color: #999;">Click "Add RDS Instance" to get started.</p></div>';
        return;
    }

    try {
        const region = getProjectRegion();
        
        // Fetch instances for all RDS instances first
        const instancesData = await Promise.all(rdsInstances.map(async (inst) => {
            const availableInstances = await fetchRDSInstancesByRegion(inst.region || region, inst.engine);
            return { inst, availableInstances };
        }));
        
        container.innerHTML = instancesData.map(({ inst, availableInstances }) => {
        const instanceOptions = availableInstances
            .filter(opt => opt.engine === inst.engine)
            .map(opt => {
                const vcpu = opt.vcpu || opt.VCPU || 0;
                const memory = opt.memory_gb || opt.MEMORY_GB || 0;
                const isSelected = inst.instance_type === opt.instance_type ? 'selected' : '';
                return `<option value="${opt.instance_type}" data-vcpu="${vcpu}" data-memory="${memory}" ${isSelected}>${opt.instance_type} (${vcpu} vCPU / ${memory} GB RAM)</option>`;
            }).join('');
        
        return `
        <div class="instance-item" style="background: #1a1a1a; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
            <div class="instance-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4 style="color: #fff; margin: 0;">RDS Instance ${rdsInstances.indexOf(inst) + 1}</h4>
                <button class="btn-remove" onclick="removeRDSInstance(${inst.id})" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">Remove</button>
            </div>
            <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Database Engine</label>
                    <select onchange="updateRDSEngine(${inst.id}, this.value)" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                        <option value="mysql" ${inst.engine === 'mysql' ? 'selected' : ''}>MySQL</option>
                        <option value="postgresql" ${inst.engine === 'postgresql' ? 'selected' : ''}>PostgreSQL</option>
                        <option value="oracle" ${inst.engine === 'oracle' ? 'selected' : ''}>Oracle</option>
                        <option value="mariadb" ${inst.engine === 'mariadb' ? 'selected' : ''}>MariaDB</option>
                        <option value="sqlserver" ${inst.engine === 'sqlserver' ? 'selected' : ''}>SQL Server</option>
                    </select>
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Instance Type</label>
                    <select onchange="updateRDSInstanceType(${inst.id}, this.value, this.options[this.selectedIndex].dataset.vcpu, this.options[this.selectedIndex].dataset.memory)" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                        <option value="">Select Instance Type</option>
                        ${instanceOptions}
                        ${inst.instance_type && !availableInstances.find(opt => opt.instance_type === inst.instance_type && opt.engine === inst.engine) ? `<option value="${inst.instance_type}" selected>${inst.instance_type} (Custom)</option>` : ''}
                    </select>
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">vCPU</label>
                    <input type="number" min="0" step="1" value="${inst.vcpu || 0}" onchange="updateRDSField(${inst.id}, 'vcpu', parseInt(this.value))" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Memory (GB)</label>
                    <input type="number" min="0" step="0.01" value="${inst.memory_gb || 0}" onchange="updateRDSField(${inst.id}, 'memory_gb', parseFloat(this.value))" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Quantity</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button onclick="updateRDSQuantity(${inst.id}, -1)" style="background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 5px; width: 35px; height: 35px; cursor: pointer;">-</button>
                        <input type="number" min="1" value="${inst.quantity}" onchange="updateRDSQuantity(${inst.id}, 0, this.value)" style="width: 80px; padding: 8px; text-align: center; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 5px;">
                        <button onclick="updateRDSQuantity(${inst.id}, 1)" style="background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 5px; width: 35px; height: 35px; cursor: pointer;">+</button>
                    </div>
                </div>
            </div>
            <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Storage (GB)</label>
                    <input type="number" min="20" value="${inst.storage_gb}" onchange="updateRDSField(${inst.id}, 'storage_gb', parseInt(this.value))" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Storage Type</label>
                    <select onchange="updateRDSField(${inst.id}, 'storage_type', this.value)" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                        <option value="gp2" ${inst.storage_type === 'gp2' ? 'selected' : ''}>gp2 - General Purpose SSD</option>
                        <option value="gp3" ${inst.storage_type === 'gp3' ? 'selected' : ''}>gp3 - General Purpose SSD</option>
                        <option value="io1" ${inst.storage_type === 'io1' ? 'selected' : ''}>io1 - Provisioned IOPS SSD</option>
                        <option value="io2" ${inst.storage_type === 'io2' ? 'selected' : ''}>io2 - Provisioned IOPS SSD</option>
                    </select>
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Deployment</label>
                    <select onchange="updateRDSField(${inst.id}, 'multi_az', this.value === 'multi_az')" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                        <option value="single_az" ${!inst.multi_az ? 'selected' : ''}>Single AZ</option>
                        <option value="multi_az" ${inst.multi_az ? 'selected' : ''}>Multi-AZ</option>
                    </select>
                </div>
                <div class="form-field">
                    <label style="color: #fff; display: block; margin-bottom: 8px; font-weight: 600;">Backup Retention (Days)</label>
                    <input type="number" min="0" max="35" value="${inst.backup_retention}" onchange="updateRDSField(${inst.id}, 'backup_retention', parseInt(this.value))" style="width: 100%; padding: 12px; background: #2a2a2a; color: #fff; border: 2px solid rgba(255, 107, 53, 0.3); border-radius: 8px;">
                </div>
            </div>
        </div>
    `;
        }).join('');
    } catch (error) {
        console.error('Error rendering RDS instances:', error);
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><p style="font-size: 16px; margin-bottom: 10px;">Error loading RDS instances.</p><p style="font-size: 14px; color: #999;">Please try again.</p></div>';
    }
}

async function updateRDSField(id, field, value) {
    const instance = rdsInstances.find(inst => inst.id === id);
    if (instance) {
        instance[field] = value;
        if (field === 'engine') {
            // Clear instance type when engine changes
            instance.instance_type = '';
            instance.vcpu = 0;
            instance.memory_gb = 0;
            window.rdsInstances = rdsInstances;
            await renderRDSInstances();
        }
        window.rdsInstances = rdsInstances;
        saveRDSInstances();
    }
}

async function updateRDSEngine(id, engine) {
    await updateRDSField(id, 'engine', engine);
}

async function updateRDSInstanceType(id, instanceType, vcpu, memoryGb) {
    const instance = rdsInstances.find(inst => inst.id === id);
    if (instance) {
        instance.instance_type = instanceType;
        instance.vcpu = parseInt(vcpu) || 0;
        instance.memory_gb = parseFloat(memoryGb) || 0;
        window.rdsInstances = rdsInstances;
        await renderRDSInstances();
        saveRDSInstances();
    }
}

function updateRDSQuantity(id, delta, value) {
    const instance = rdsInstances.find(inst => inst.id === id);
    if (instance) {
        if (value !== undefined) {
            instance.quantity = Math.max(1, parseInt(value));
        } else {
            instance.quantity = Math.max(1, instance.quantity + delta);
        }
        renderRDSInstances();
        saveRDSInstances();
    }
}

async function saveRDSInstances() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        console.warn('No project ID, skipping RDS save');
        return;
    }

    // Validate RDS instances before saving
    const validInstances = rdsInstances.filter(inst => {
        return inst.engine && inst.instance_type && inst.region;
    });
    
    if (validInstances.length === 0 && rdsInstances.length > 0) {
        console.warn('No valid RDS instances to save');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'save_rds');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(validInstances.length > 0 ? validInstances : rdsInstances));

        const response = await fetch('../controllers/service_controller.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response.');
        }
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error('Invalid JSON response from server.');
        }
        
        if (result.success) {
            await loadRDSInstances();
        } else {
            alert('Error saving RDS instances: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving RDS instances:', error);
        alert('Error saving RDS instances: ' + error.message);
    }
}

async function loadRDSInstances() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;

    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_rds&project_id=${projectId}`);
        const result = await response.json();
        if (result.success) {
            if (result.config) {
                // Handle both single config (backward compatibility) and array
                if (Array.isArray(result.config)) {
                    rdsInstances = result.config.map(inst => ({
                        ...inst,
                        id: inst.id || Date.now() + Math.random(),
                        multi_az: inst.multi_az == 1 || inst.multi_az === true
                    }));
                } else {
                    // Single config - convert to array
                    rdsInstances = [{
                        ...result.config,
                        id: result.config.id || Date.now(),
                        multi_az: result.config.multi_az == 1 || result.config.multi_az === true
                    }];
                }
            } else {
                rdsInstances = [];
            }
            window.rdsInstances = rdsInstances;
            renderRDSInstances();
        }
    } catch (error) {
        console.error('Error loading RDS instances:', error);
    }
}

window.rdsInstances = rdsInstances;

// S3 Service
let s3Config = {
    storage_class: 'standard',
    storage_gb: 100,
    requests_million: 0,
    data_transfer_gb: 0
};

function loadS3Form(container) {
    container.innerHTML = `
        <h2>S3 Configuration</h2>
        <div class="service-form-content">
            <form id="s3Form">
            <div class="form-row">
                <div class="form-field">
                    <label>Storage Class</label>
                    <select id="s3_storage_class" onchange="updateS3Field('storage_class', this.value)">
                        <option value="standard">Standard</option>
                        <option value="intelligent_tiering">Intelligent-Tiering</option>
                        <option value="standard_ia">Standard-IA</option>
                        <option value="onezone_ia">One Zone-IA</option>
                        <option value="glacier">Glacier</option>
                        <option value="deep_archive">Deep Archive</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Storage (GB)</label>
                    <input type="number" min="1" id="s3_storage" value="${s3Config.storage_gb}" onchange="updateS3Field('storage_gb', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Requests (Million)</label>
                    <input type="number" min="0" step="0.1" id="s3_requests" value="${s3Config.requests_million}" onchange="updateS3Field('requests_million', parseFloat(this.value))">
                </div>
                <div class="form-field">
                    <label>Data Transfer (GB)</label>
                    <input type="number" min="0" id="s3_transfer" value="${s3Config.data_transfer_gb}" onchange="updateS3Field('data_transfer_gb', parseInt(this.value))">
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveS3Config()">Save S3 Configuration</button>
            </form>
        </div>
    `;
    loadS3Config();
}

function updateS3Field(field, value) {
    s3Config[field] = value;
}

async function saveS3Config() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'save_s3');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(s3Config));

        const response = await fetch('../controllers/service_controller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            alert('S3 configuration saved successfully!');
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error saving S3 config:', error);
        alert('An error occurred. Please try again.');
    }
}

async function loadS3Config() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;

    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_s3&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            s3Config = result.config;
            if (document.getElementById('s3_storage_class')) document.getElementById('s3_storage_class').value = s3Config.storage_class;
            if (document.getElementById('s3_storage')) document.getElementById('s3_storage').value = s3Config.storage_gb;
            if (document.getElementById('s3_requests')) document.getElementById('s3_requests').value = s3Config.requests_million;
            if (document.getElementById('s3_transfer')) document.getElementById('s3_transfer').value = s3Config.data_transfer_gb;
        }
    } catch (error) {
        console.error('Error loading S3 config:', error);
    }
}

// RDS Service
let rdsConfig = {
    engine: 'mysql',
    instance_type: 'db.t3.micro',
    quantity: 1,
    storage_gb: 20,
    storage_type: 'gp2',
    multi_az: false,
    backup_retention: 7,
    region: 'us-east-1'
};

// RDS form loading is now handled above in the updated section

// EKS Service
let eksConfig = {
    cluster_count: 1,
    node_group_count: 1,
    node_count: 1,
    region: ''
};

function loadEKSForm(container) {
    container.innerHTML = `
        <h2>EKS Configuration</h2>
        <div class="service-form-content">
            <form id="eksForm">
            <div class="form-row">
                <div class="form-field">
                    <label>Cluster Count</label>
                    <input type="number" min="1" id="eks_cluster" value="${eksConfig.cluster_count}" onchange="updateEKSField('cluster_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Node Group Count</label>
                    <input type="number" min="1" id="eks_nodegroup" value="${eksConfig.node_group_count}" onchange="updateEKSField('node_group_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Node Count</label>
                    <input type="number" min="1" id="eks_nodes" value="${eksConfig.node_count}" onchange="updateEKSField('node_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Region</label>
                    ${getEKSRegionSelectHTML(eksConfig.region || getProjectRegion())}
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveEKSConfig()">Save EKS Configuration</button>
            </form>
        </div>
    `;
    loadEKSConfig();
}

function updateEKSField(field, value) {
    eksConfig[field] = value;
}

async function saveEKSConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'save_eks');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(eksConfig));
        const response = await fetch('../controllers/service_controller.php', { method: 'POST', body: formData });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            alert('Error: Server returned non-JSON response. Please check console for details.');
            return;
        }
        
        const result = await response.json();
        if (result.success) alert('EKS configuration saved successfully!');
        else alert('Error: ' + (result.error || 'Failed to save EKS configuration'));
    } catch (error) {
        console.error('Error saving EKS config:', error);
        alert('Error saving EKS configuration. Please check console for details.');
    }
}

async function loadEKSConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;
    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_eks&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            eksConfig = result.config;
            if (document.getElementById('eks_cluster')) document.getElementById('eks_cluster').value = eksConfig.cluster_count;
            if (document.getElementById('eks_nodegroup')) document.getElementById('eks_nodegroup').value = eksConfig.node_group_count;
            if (document.getElementById('eks_nodes')) document.getElementById('eks_nodes').value = eksConfig.node_count;
            if (document.getElementById('eks_region')) document.getElementById('eks_region').value = eksConfig.region;
        }
    } catch (error) {
        console.error('Error loading EKS config:', error);
    }
}

// ECR Service
let ecrConfig = {
    storage_gb: 10,
    data_transfer_gb: 0
};

function loadECRForm(container) {
    container.innerHTML = `
        <h2>ECR Configuration</h2>
        <div class="service-form-content">
            <form id="ecrForm">
            <div class="form-row">
                <div class="form-field">
                    <label>Storage (GB)</label>
                    <input type="number" min="1" id="ecr_storage" value="${ecrConfig.storage_gb}" onchange="updateECRField('storage_gb', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Data Transfer (GB)</label>
                    <input type="number" min="0" id="ecr_transfer" value="${ecrConfig.data_transfer_gb}" onchange="updateECRField('data_transfer_gb', parseInt(this.value))">
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveECRConfig()">Save ECR Configuration</button>
            </form>
        </div>
    `;
    loadECRConfig();
}

function updateECRField(field, value) {
    ecrConfig[field] = value;
}

async function saveECRConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'save_ecr');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(ecrConfig));
        const response = await fetch('../controllers/service_controller.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) alert('ECR configuration saved successfully!');
        else alert('Error: ' + result.error);
    } catch (error) {
        console.error('Error saving ECR config:', error);
        alert('An error occurred. Please try again.');
    }
}

async function loadECRConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;
    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_ecr&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            ecrConfig = result.config;
            if (document.getElementById('ecr_storage')) document.getElementById('ecr_storage').value = ecrConfig.storage_gb;
            if (document.getElementById('ecr_transfer')) document.getElementById('ecr_transfer').value = ecrConfig.data_transfer_gb;
        }
    } catch (error) {
        console.error('Error loading ECR config:', error);
    }
}

// Load Balancer Service
let lbConfig = {
    load_balancer_type: 'application',
    quantity: 1,
    region: 'us-east-1',
    data_processed_gb: 0
};

function loadLOADBALANCERForm(container) {
    container.innerHTML = `
        <h2>Load Balancer Configuration</h2>
        <div class="service-form-content">
            <form id="lbForm">
            <div class="form-row">
                <div class="form-field">
                    <label>Load Balancer Type</label>
                    <select id="lb_type" onchange="updateLBField('load_balancer_type', this.value)">
                        <option value="application">Application Load Balancer</option>
                        <option value="network">Network Load Balancer</option>
                        <option value="classic">Classic Load Balancer</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Quantity</label>
                    <input type="number" min="1" id="lb_quantity" value="${lbConfig.quantity}" onchange="updateLBField('quantity', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Region</label>
                    ${getLBRegionSelectHTML(lbConfig.region || getProjectRegion())}
                </div>
                <div class="form-field">
                    <label>Data Processed (GB)</label>
                    <input type="number" min="0" id="lb_data" value="${lbConfig.data_processed_gb}" onchange="updateLBField('data_processed_gb', parseInt(this.value))">
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveLBConfig()">Save Load Balancer Configuration</button>
            </form>
        </div>
    `;
    loadLBConfig();
}

function updateLBField(field, value) {
    lbConfig[field] = value;
}

async function saveLBConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'save_loadbalancer');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(lbConfig));
        const response = await fetch('../controllers/service_controller.php', { method: 'POST', body: formData });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            alert('Error: Server returned non-JSON response. Please check console for details.');
            return;
        }
        
        const result = await response.json();
        if (result.success) alert('Load Balancer configuration saved successfully!');
        else alert('Error: ' + (result.error || 'Failed to save Load Balancer configuration'));
    } catch (error) {
        console.error('Error saving LB config:', error);
        alert('Error saving Load Balancer configuration. Please check console for details.');
    }
}

async function loadLBConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;
    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_loadbalancer&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            lbConfig = result.config;
            if (document.getElementById('lb_type')) document.getElementById('lb_type').value = lbConfig.load_balancer_type;
            if (document.getElementById('lb_quantity')) document.getElementById('lb_quantity').value = lbConfig.quantity;
            if (document.getElementById('lb_region')) document.getElementById('lb_region').value = lbConfig.region;
            if (document.getElementById('lb_data')) document.getElementById('lb_data').value = lbConfig.data_processed_gb;
        }
    } catch (error) {
        console.error('Error loading LB config:', error);
    }
}

// WAF Service
let wafConfig = {
    web_acl_count: 1,
    rules_count: 0,
    requests_million: 0
};

function loadWAFForm(container) {
    container.innerHTML = `
        <h2>WAF Configuration</h2>
        <div class="service-form-content">
            <form id="wafForm">
            <div class="form-row">
                <div class="form-field">
                    <label>Web ACL Count</label>
                    <input type="number" min="1" id="waf_acl" value="${wafConfig.web_acl_count}" onchange="updateWAFField('web_acl_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Rules Count</label>
                    <input type="number" min="0" id="waf_rules" value="${wafConfig.rules_count}" onchange="updateWAFField('rules_count', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Requests (Million)</label>
                    <input type="number" min="0" step="0.1" id="waf_requests" value="${wafConfig.requests_million}" onchange="updateWAFField('requests_million', parseFloat(this.value))">
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveWAFConfig()">Save WAF Configuration</button>
            </form>
        </div>
    `;
    loadWAFConfig();
}

function updateWAFField(field, value) {
    wafConfig[field] = value;
}

async function saveWAFConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'save_waf');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(wafConfig));
        const response = await fetch('../controllers/service_controller.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) alert('WAF configuration saved successfully!');
        else alert('Error: ' + result.error);
    } catch (error) {
        console.error('Error saving WAF config:', error);
        alert('An error occurred. Please try again.');
    }
}

async function loadWAFConfig() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;
    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_waf&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            wafConfig = result.config;
            if (document.getElementById('waf_acl')) document.getElementById('waf_acl').value = wafConfig.web_acl_count;
            if (document.getElementById('waf_rules')) document.getElementById('waf_rules').value = wafConfig.rules_count;
            if (document.getElementById('waf_requests')) document.getElementById('waf_requests').value = wafConfig.requests_million;
        }
    } catch (error) {
        console.error('Error loading WAF config:', error);
    }
}

// Route53 Service
let route53Config = {
    hosted_zones: 1,
    queries_million: 0,
    health_checks: 0
};

function loadROUTE53Form(container) {
    container.innerHTML = `
        <h2>Route 53 Configuration</h2>
        <div class="service-form-content">
            <form id="route53Form">
            <div class="form-row">
                <div class="form-field">
                    <label>Hosted Zones</label>
                    <input type="number" min="1" id="route53_zones" value="${route53Config.hosted_zones}" onchange="updateRoute53Field('hosted_zones', parseInt(this.value))">
                </div>
                <div class="form-field">
                    <label>Queries (Million)</label>
                    <input type="number" min="0" step="0.1" id="route53_queries" value="${route53Config.queries_million}" onchange="updateRoute53Field('queries_million', parseFloat(this.value))">
                </div>
                <div class="form-field">
                    <label>Health Checks</label>
                    <input type="number" min="0" id="route53_health" value="${route53Config.health_checks}" onchange="updateRoute53Field('health_checks', parseInt(this.value))">
                </div>
            </div>
            <button type="button" class="btn-success" onclick="saveRoute53Config()">Save Route 53 Configuration</button>
            </form>
        </div>
    `;
    loadRoute53Config();
}

function updateRoute53Field(field, value) {
    route53Config[field] = value;
}

async function saveRoute53Config() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) {
        alert('Please save your project first!');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'save_route53');
        formData.append('project_id', projectId);
        formData.append('config', JSON.stringify(route53Config));
        const response = await fetch('../controllers/service_controller.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) alert('Route 53 configuration saved successfully!');
        else alert('Error: ' + result.error);
    } catch (error) {
        console.error('Error saving Route53 config:', error);
        alert('An error occurred. Please try again.');
    }
}

async function loadRoute53Config() {
    const projectId = document.getElementById('project_id')?.value;
    if (!projectId) return;
    try {
        const response = await fetch(`../controllers/service_controller.php?action=get_route53&project_id=${projectId}`);
        const result = await response.json();
        if (result.success && result.config) {
            route53Config = result.config;
            if (document.getElementById('route53_zones')) document.getElementById('route53_zones').value = route53Config.hosted_zones;
            if (document.getElementById('route53_queries')) document.getElementById('route53_queries').value = route53Config.queries_million;
            if (document.getElementById('route53_health')) document.getElementById('route53_health').value = route53Config.health_checks;
        }
    } catch (error) {
        console.error('Error loading Route53 config:', error);
    }
}

