terraform {
  required_providers {
    render = {
      source = "render-oss/render"
      version = "~> 1.0"
    }
  }
}

provider "render" {
  api_key = var.render_api_key
  owner_id = "tea-d1hlc6vfte5s73abvab0" 
}

# Variable pour la clé API Render
variable "render_api_key" {
  description = "Render API key"
  type        = string
  sensitive   = true
}

# Variable pour le token Papertrail
variable "papertrail_token" {
  description = "Ton token Papertrail"
  type        = string
  sensitive   = true
  default     = "hVEc15AdNgNV65kzNCpehWxuI49gLMWcTonnuGEDAuKTTc6m-lMLLRKjxK5Mx6A8z5bhsRw"
}

# SERVICE BACKEND
resource "render_web_service" "openhub_backend" {
  name   = "openhub-backend"
  plan   = "free"
  region = "ohio"
  
  runtime_source = {
    docker = {
      repo_url = "https://github.com/Mhalat1/OpenHub"
      branch   = "main"
      dockerfile_path    = "Dockerfile"
      build_context_path = "backend"  # ← REMIS À "backend" (pas ".")
    }
  }

  root_directory = "backend"  # ← AJOUTÉ !
  
  env_vars = {
    APP_ENV = { value = "prod" }
    APP_DEBUG = { value = "0" }
    DATABASE_URL = { value = var.database_url }
    PAPERTRAIL_URL = { value = "https://logs.collector.eu-01.cloud.solarwinds.com/v1/logs" }
    PAPERTRAIL_TOKEN = { value = var.papertrail_token }
    APACHE_DOCUMENT_ROOT = { value = "/opt/render/project/src/backend/public" }
  }
  
  health_check_path = "/health"
}

# SERVICE FRONTEND
resource "render_web_service" "openhub_frontend" {
  name   = "openhub-frontend"
  plan   = "free"
  region = "ohio"
  
  runtime_source = {
    docker = {
      repo_url = "https://github.com/Mhalat1/OpenHub"
      branch   = "main"
      dockerfile_path    = "Dockerfile"
      build_context_path = "frontend"  # ← REMIS À "frontend" (pas ".")
    }
  }

  root_directory = "frontend"  # ← GARDE CECI
  
  env_vars = {
    REACT_APP_API_URL = { value = render_web_service.openhub_backend.url }
    NODE_ENV = { value = "production" }
    VITE_API_URL = { value = render_web_service.openhub_backend.url }
  }
  
  health_check_path = "/"
}

# OUTPUTS
output "backend_url" {
  value = render_web_service.openhub_backend.url
}

output "frontend_url" {
  value = render_web_service.openhub_frontend.url
}