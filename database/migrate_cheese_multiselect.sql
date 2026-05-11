-- Run this in Supabase SQL Editor
-- Adds included_free to modifier_groups and updates cheese to multi-select

-- 1. Add included_free column (how many selections are free before charging)
ALTER TABLE modifier_groups
  ADD COLUMN IF NOT EXISTS included_free INT NOT NULL DEFAULT 1;

-- 2. Update cheese group: allow up to 3 cheeses, 1 included free
UPDATE modifier_groups SET
  ui_type       = 'checkbox',
  min_select    = 0,
  max_select    = 3,
  included_free = 1
WHERE id = '00000000-0000-0002-0000-000000000003';

-- 3. Set extra cheese price ($1.00 each beyond the free one)
-- price_delta on options = charge per selection when over included_free limit
-- We store price_delta=1.00 on each cheese option so the app knows the per-cheese upcharge
UPDATE modifier_options SET price_delta = 1.00
WHERE modifier_group_id = '00000000-0000-0002-0000-000000000003';
