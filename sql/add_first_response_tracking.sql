-- Item 30: "Only keep the WhatsApp-under-1-hour promise if you can hit it."
-- WhatsApp replies happen outside this codebase entirely, so the closest
-- honest proxy we can measure is: time from a guest-initiated inquiry
-- landing in our system, to the first time staff acts on it here (marks
-- a contact message read, or moves a custom-safari request off "new").
-- Nullable — most historical rows won't have this.
ALTER TABLE contact_messages
  ADD COLUMN first_response_at DATETIME NULL AFTER is_read;

ALTER TABLE custom_safari_requests
  ADD COLUMN first_response_at DATETIME NULL AFTER status;
