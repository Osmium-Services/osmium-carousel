SELECT S.id, S.title, S.image_filename, S.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS S
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'carousel_slide' AND record_id = S.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE S.deleted_at IS NOT NULL
ORDER BY S.deleted_at DESC
