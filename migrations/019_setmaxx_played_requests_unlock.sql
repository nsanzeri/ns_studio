UPDATE `setmaxx_requests`
SET `active_lock` = NULL
WHERE `status` IN ('played', 'declined', 'canceled');
