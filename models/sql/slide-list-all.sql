SELECT S.*, P.url AS page_url
FROM {TABLE} AS S
LEFT JOIN {PREFIX}pages AS P ON P.id = S.link_page_id AND P.deleted_at IS NULL
WHERE S.deleted_at IS NULL
ORDER BY (S.group_name IS NULL) DESC, S.group_name ASC, S.sort_order ASC, S.id ASC
