-- Migration: Add profit column to scores table for money-based game mode
-- Run: mysql -h db -u root -proot itec106 < core/migration_profit.sql

ALTER TABLE scores ADD COLUMN profit DECIMAL(10,2) DEFAULT NULL AFTER streak;
