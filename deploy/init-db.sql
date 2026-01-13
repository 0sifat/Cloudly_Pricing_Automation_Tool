-- Database initialization script for Docker
-- This file will be executed when the database container is first created

-- The database and user are already created by environment variables
-- This file contains the complete schema

USE aws_calc;

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    salesman_name VARCHAR(255) NOT NULL,
    region VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- AWS SERVICE CONFIGURATION TABLES
-- ============================================================================

-- EC2 Instances Table
CREATE TABLE IF NOT EXISTS ec2_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    instance_type VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 1,
    operating_system VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    vcpu INT DEFAULT 0,
    memory_gb DECIMAL(5, 2) DEFAULT 0.00,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- EC2 Pricing Models Table
CREATE TABLE IF NOT EXISTS ec2_pricing_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ec2_instance_id INT NOT NULL,
    pricing_model VARCHAR(50) NOT NULL,
    reservation_term INT DEFAULT 0,
    payment_option VARCHAR(50) DEFAULT 'no_upfront',
    utilization VARCHAR(50) DEFAULT '100',
    spot_discount VARCHAR(50) DEFAULT '70',
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ec2_instance_id) REFERENCES ec2_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- EBS Volumes Table
CREATE TABLE IF NOT EXISTS ebs_volumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    ec2_instance_id INT,
    server_type VARCHAR(50) NOT NULL,
    server_name VARCHAR(100),
    volume_type VARCHAR(50) NOT NULL,
    size_gb INT NOT NULL,
    iops INT DEFAULT 0,
    throughput INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (ec2_instance_id) REFERENCES ec2_instances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VPC Configuration Table
CREATE TABLE IF NOT EXISTS vpc_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    region VARCHAR(50) NOT NULL,
    vpc_count INT DEFAULT 1,
    availability_zones INT DEFAULT 2,
    nat_gateway_count INT DEFAULT 0,
    vpc_endpoint_count INT DEFAULT 0,
    data_transfer_gb INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- S3 Configuration Table
CREATE TABLE IF NOT EXISTS s3_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    storage_class VARCHAR(50) NOT NULL,
    storage_gb INT NOT NULL,
    requests_million INT DEFAULT 0,
    data_transfer_gb INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RDS Configuration Table
CREATE TABLE IF NOT EXISTS rds_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    engine VARCHAR(50) NOT NULL,
    instance_type VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 1,
    storage_gb INT NOT NULL,
    storage_type VARCHAR(50) NOT NULL,
    multi_az BOOLEAN DEFAULT FALSE,
    backup_retention INT DEFAULT 7,
    region VARCHAR(50) NOT NULL,
    vcpu INT DEFAULT 0,
    memory_gb DECIMAL(5, 2) DEFAULT 0.00,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- EKS Configuration Table
CREATE TABLE IF NOT EXISTS eks_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    cluster_count INT DEFAULT 1,
    node_group_count INT DEFAULT 1,
    instance_type VARCHAR(50) NOT NULL,
    node_count INT DEFAULT 1,
    region VARCHAR(50) NOT NULL,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ECR Configuration Table
CREATE TABLE IF NOT EXISTS ecr_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    storage_gb INT NOT NULL,
    data_transfer_gb INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Load Balancer Configuration Table
CREATE TABLE IF NOT EXISTS load_balancer_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    load_balancer_type VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 1,
    region VARCHAR(50) NOT NULL,
    data_processed_gb INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WAF Configuration Table
CREATE TABLE IF NOT EXISTS waf_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    web_acl_count INT DEFAULT 1,
    rules_count INT DEFAULT 0,
    requests_million INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route53 Configuration Table
CREATE TABLE IF NOT EXISTS route53_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    hosted_zones INT DEFAULT 1,
    queries_million INT DEFAULT 0,
    health_checks INT DEFAULT 0,
    unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project Totals Table
CREATE TABLE IF NOT EXISTS project_totals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    total_unit_cost DECIMAL(10, 2) DEFAULT 0.00,
    total_estimated_cost DECIMAL(10, 2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice History Table
CREATE TABLE IF NOT EXISTS invoice_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    invoice_pdf_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PRICING TABLES FOR ADMIN MANAGEMENT
-- ============================================================================

-- EC2 Instance Pricing
CREATE TABLE IF NOT EXISTS ec2_instance_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_type VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    vcpu INT DEFAULT 0,
    memory_gb DECIMAL(5, 2) DEFAULT 0.00,
    on_demand_price_per_hour DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_instance_region (instance_type, region),
    INDEX idx_instance_region (instance_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- EBS Volume Pricing
CREATE TABLE IF NOT EXISTS ebs_volume_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volume_type VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_gb_per_month DECIMAL(10, 6) DEFAULT 0.000000,
    iops_price_per_iops DECIMAL(10, 6) DEFAULT 0.000000,
    throughput_price_per_mbps DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_volume_region (volume_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- S3 Storage Pricing
CREATE TABLE IF NOT EXISTS s3_storage_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    storage_class VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_gb_per_month DECIMAL(10, 6) DEFAULT 0.000000,
    request_price_per_1000 DECIMAL(10, 6) DEFAULT 0.000000,
    data_transfer_price_per_gb DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_storage_region (storage_class, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RDS Instance Pricing
CREATE TABLE IF NOT EXISTS rds_instance_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_type VARCHAR(100) NOT NULL,
    engine VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    vcpu INT DEFAULT 0,
    memory_gb DECIMAL(5, 2) DEFAULT 0.00,
    on_demand_price_per_hour DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rds_instance (instance_type, engine, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RDS Storage Pricing
CREATE TABLE IF NOT EXISTS rds_storage_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    storage_type VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_gb_per_month DECIMAL(10, 6) DEFAULT 0.000000,
    iops_price_per_iops DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rds_storage (storage_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VPC Pricing
CREATE TABLE IF NOT EXISTS vpc_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_hour DECIMAL(10, 6) DEFAULT 0.000000,
    price_per_gb DECIMAL(10, 6) DEFAULT 0.000000,
    unit VARCHAR(50) DEFAULT 'hour',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vpc_service (service_name, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WAF Pricing
CREATE TABLE IF NOT EXISTS waf_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_type VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_unit DECIMAL(10, 6) DEFAULT 0.000000,
    unit VARCHAR(50) DEFAULT 'month',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_waf_pricing (pricing_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Load Balancer Pricing
CREATE TABLE IF NOT EXISTS load_balancer_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    load_balancer_type VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_hour DECIMAL(10, 6) DEFAULT 0.000000,
    price_per_gb DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lb_pricing (load_balancer_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- EKS Pricing
CREATE TABLE IF NOT EXISTS eks_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_type VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_hour DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_eks_pricing (pricing_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ECR Pricing
CREATE TABLE IF NOT EXISTS ecr_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_type VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_gb_per_month DECIMAL(10, 6) DEFAULT 0.000000,
    price_per_gb DECIMAL(10, 6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ecr_pricing (pricing_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route53 Pricing
CREATE TABLE IF NOT EXISTS route53_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_type VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    price_per_unit DECIMAL(10, 6) DEFAULT 0.000000,
    unit VARCHAR(50) DEFAULT 'month',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_route53_pricing (pricing_type, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
