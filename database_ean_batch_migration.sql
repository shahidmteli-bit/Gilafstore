-- ============================================
-- EAN MANDATORY FOR BATCH GENERATION - MIGRATION
-- Adds weight_id column to batch_codes table
-- Run this in phpMyAdmin SQL tab
-- ============================================

-- Add weight_id column to batch_codes table
-- This will fail silently if column already exists
ALTER TABLE `batch_codes` ADD COLUMN `weight_id` INT NULL AFTER `net_weight`;

-- ============================================
-- NOTES:
-- 1. This migration adds weight_id to batch_codes table
-- 2. weight_id links batch to product_weights for EAN lookup
-- 3. EAN is now required for batch generation
-- 4. Batch code format now includes EAN suffix: G-XX-MMYY-DD-S-EE
--    where EE = last 2 digits of EAN
-- ============================================
