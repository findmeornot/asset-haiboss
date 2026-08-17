<?php

namespace App\Enums;

enum AuditAction: string
{
    // Authentication
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case LOGIN_FAILED = 'login_failed';
    case PASSWORD_CHANGED = 'password_changed';
    case PASSWORD_RESET = 'password_reset';

    // Model CRUD (Asset, Location, etc)
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    
    // Asset Specific
    case PRICE_CHANGE = 'price_change';
    case LOCATION_CHANGE = 'location_change';
    case PIC_CHANGE = 'pic_change';
    case STATUS_CHANGE = 'status_change';
    
    // Media
    case PHOTO_UPLOADED = 'photo_uploaded';
    case PHOTO_DELETED = 'photo_deleted';

    // Mutation
    case MUTATION_CREATED = 'mutation_created';
    case MUTATION_UPDATED = 'mutation_updated';
    case MUTATION_APPROVED = 'mutation_approved';
    case MUTATION_REJECTED = 'mutation_rejected';
    case MUTATION_COMPLETED = 'mutation_completed';

    // Import/Export
    case IMPORT_STARTED = 'import_started';
    case IMPORT_COMPLETED = 'import_completed';
    case IMPORT_FAILED = 'import_failed';
    case EXPORT_EXCEL = 'export_excel';
    case EXPORT_PDF = 'export_pdf';

    // User/Auth
    case USER_CREATED = 'user_created';
    case USER_UPDATED = 'user_updated';
    case USER_DISABLED = 'user_disabled';
    case ROLE_CHANGED = 'role_changed';
    case PERMISSION_CHANGED = 'permission_changed';
    
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
