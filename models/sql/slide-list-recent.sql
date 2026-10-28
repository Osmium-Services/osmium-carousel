SELECT S.id, S.title, S.created_at, S.updated_at, S.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS S
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'carousel_slide' AND record_id = S.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE (S.created_at >= NOW() - INTERVAL 24 HOUR OR S.updated_at >= NOW() - INTERVAL 24 HOUR OR S.deleted_at >= NOW() - INTERVAL 24 HOUR)
ORDER BY GREATEST(COALESCE(S.updated_at, S.created_at), S.created_at, COALESCE(S.deleted_at, S.created_at)) DESC, S.id DESC
LIMIT 10
