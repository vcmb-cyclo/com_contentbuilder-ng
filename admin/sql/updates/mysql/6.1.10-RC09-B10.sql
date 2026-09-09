ALTER TABLE `#__contentbuilderng_forms`
    MODIFY `initial_order_dir2` VARCHAR(4) NOT NULL DEFAULT 'asc';

ALTER TABLE `#__contentbuilderng_forms`
    MODIFY `initial_order_dir3` VARCHAR(4) NOT NULL DEFAULT 'asc';

UPDATE `#__contentbuilderng_forms`
SET `initial_order_dir2` = 'asc'
WHERE `initial_sort_order2` = -1;

UPDATE `#__contentbuilderng_forms`
SET `initial_order_dir3` = 'asc'
WHERE `initial_sort_order3` = -1;
