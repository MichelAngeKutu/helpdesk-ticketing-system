-- Help Desk Ticketing & Access Management System
-- Database schema for Microsoft SQL Server
--
-- Run in SSMS after creating an empty database:
--   CREATE DATABASE HelpDesk;
--   GO
--   USE HelpDesk;
--   GO

CREATE TABLE users (
    id            INT IDENTITY(1,1) PRIMARY KEY,
    username      NVARCHAR(50)  NOT NULL UNIQUE,
    full_name     NVARCHAR(100) NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    role          NVARCHAR(20)  NOT NULL DEFAULT 'user'
                  CHECK (role IN ('user', 'technician', 'admin')),
    created_at    DATETIME2     NOT NULL
);

-- System permissions a user can request through the access workflow
CREATE TABLE permissions (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    code        NVARCHAR(50)  NOT NULL UNIQUE,
    description NVARCHAR(255) NOT NULL
);

-- Permissions that have been granted (only ever after an approved request)
CREATE TABLE user_permissions (
    user_id       INT NOT NULL REFERENCES users(id),
    permission_id INT NOT NULL REFERENCES permissions(id),
    granted_by    INT NOT NULL REFERENCES users(id),
    granted_at    DATETIME2 NOT NULL,
    PRIMARY KEY (user_id, permission_id)
);

CREATE TABLE tickets (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    title       NVARCHAR(150) NOT NULL,
    description NVARCHAR(MAX) NOT NULL,
    priority    NVARCHAR(10)  NOT NULL DEFAULT 'medium'
                CHECK (priority IN ('low', 'medium', 'high')),
    status      NVARCHAR(20)  NOT NULL DEFAULT 'open'
                CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')),
    created_by  INT NOT NULL REFERENCES users(id),
    assigned_to INT NULL REFERENCES users(id),
    created_at  DATETIME2 NOT NULL,
    updated_at  DATETIME2 NOT NULL
);

CREATE TABLE ticket_comments (
    id         INT IDENTITY(1,1) PRIMARY KEY,
    ticket_id  INT NOT NULL REFERENCES tickets(id),
    user_id    INT NOT NULL REFERENCES users(id),
    body       NVARCHAR(MAX) NOT NULL,
    created_at DATETIME2 NOT NULL
);

CREATE TABLE access_requests (
    id            INT IDENTITY(1,1) PRIMARY KEY,
    user_id       INT NOT NULL REFERENCES users(id),
    permission_id INT NOT NULL REFERENCES permissions(id),
    reason        NVARCHAR(500) NOT NULL,
    status        NVARCHAR(20)  NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending', 'approved', 'denied')),
    reviewed_by   INT NULL REFERENCES users(id),
    review_note   NVARCHAR(500) NULL,
    created_at    DATETIME2 NOT NULL,
    reviewed_at   DATETIME2 NULL
);

-- Every login, status change and access decision is recorded here
CREATE TABLE audit_log (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    user_id     INT NULL REFERENCES users(id),
    action      NVARCHAR(50)  NOT NULL,
    entity_type NVARCHAR(30)  NULL,
    entity_id   INT           NULL,
    details     NVARCHAR(500) NULL,
    ip_address  NVARCHAR(45)  NULL,
    created_at  DATETIME2     NOT NULL
);

CREATE INDEX ix_tickets_created_by  ON tickets(created_by);
CREATE INDEX ix_tickets_assigned_to ON tickets(assigned_to);
CREATE INDEX ix_audit_created_at    ON audit_log(created_at);

INSERT INTO permissions (code, description) VALUES
    ('tickets.view_all', 'View every ticket in the system, not only your own'),
    ('reports.view',     'View help desk reports'),
    ('kb.edit',          'Edit knowledge base articles');
