UPDATE {TABLE}
SET title = :title,
    group_name = :group_name,
    summary = :summary,
    button_text = :button_text,
    link_type = :link_type,
    link_page_id = :link_page_id,
    link_url = :link_url,
    enabled = :enabled,
    sort_order = :sort_order,
    schedule_start = :schedule_start,
    schedule_end = :schedule_end,
    schedule_recurring = :schedule_recurring,
    exclusive = :exclusive,
    promotion_notes = :promotion_notes
WHERE id = :id
