INSERT INTO {TABLE}
    (title, group_name, summary, button_text, link_type, link_page_id, link_url, enabled, sort_order,
     schedule_start, schedule_end, schedule_recurring, exclusive, promotion_notes)
VALUES
    (:title, :group_name, :summary, :button_text, :link_type, :link_page_id, :link_url, :enabled, :sort_order,
     :schedule_start, :schedule_end, :schedule_recurring, :exclusive, :promotion_notes)
