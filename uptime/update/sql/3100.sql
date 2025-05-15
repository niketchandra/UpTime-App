UPDATE `settings` SET `value` = '{\"version\":\"31.0.0\", \"code\":\"3100\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

alter table plans drop codes_ids;

-- SEPARATOR --


alter table codes modify days int unsigned null;

-- SEPARATOR --


alter table codes modify discount int unsigned not null;

-- SEPARATOR --


alter table codes modify quantity int unsigned default 1 not null;

-- SEPARATOR --


alter table codes modify redeemed int unsigned default 0 not null;

-- SEPARATOR --


alter table codes add plans_ids text null after redeemed;

-- SEPARATOR --


alter table incidents add user_id bigint unsigned null after incident_id;

-- SEPARATOR --


alter table incidents add key `user_id` (`user_id`);

-- SEPARATOR --


alter table incidents
  ADD CONSTRAINT FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- SEPARATOR --


alter table incidents add comment text null after end_heartbeat_log_id;

-- SEPARATOR --


alter table server_monitors add uptime int null after notifications;

-- SEPARATOR --


alter table server_monitors add network_total_download int null after uptime;

-- SEPARATOR --


alter table server_monitors add network_download int null after network_total_download;

-- SEPARATOR --


alter table server_monitors add network_total_upload int null after network_download;

-- SEPARATOR --


alter table server_monitors add network_upload int null after network_total_upload;

-- SEPARATOR --


alter table server_monitors add os_name text null after network_upload;

-- SEPARATOR --


alter table server_monitors add os_version text null after os_name;

-- SEPARATOR --


alter table server_monitors add kernel_name text null after os_version;

-- SEPARATOR --


alter table server_monitors add kernel_version text null after kernel_name;

-- SEPARATOR --


alter table server_monitors add kernel_release text null after kernel_version;

-- SEPARATOR --


alter table server_monitors add cpu_architecture text null after kernel_release;

-- SEPARATOR --


alter table server_monitors add cpu_model text null after cpu_usage;

-- SEPARATOR --


alter table server_monitors add cpu_cores int null after cpu_model;

-- SEPARATOR --


alter table server_monitors add cpu_frequency int null after cpu_cores;

-- SEPARATOR --


alter table server_monitors add ram_used int null after ram_usage;

-- SEPARATOR --


alter table server_monitors add ram_total int null after ram_used;

-- SEPARATOR --


alter table server_monitors add disk_used int null after disk_usage;

-- SEPARATOR --


alter table server_monitors add disk_total int null after disk_used;

-- SEPARATOR --


alter table server_monitors_logs add network_download int null after cpu_load_15;

-- SEPARATOR --


alter table server_monitors_logs add network_upload int null after network_download;

-- SEPARATOR --

