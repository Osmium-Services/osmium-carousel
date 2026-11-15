SELECT COUNT(*) AS total FROM {TABLE} WHERE id != :id AND image_filename = :name AND (:live_only = 0 OR deleted_at IS NULL)
