Phase 1 - MVP (Must Have):
GET /api/projects (list)
POST /api/projects (create)
GET /api/projects/{id} (view)
PATCH /api/projects/{id} (update)
DELETE /api/projects/{id} (delete)
Phase 2 - Destinations:
GET /api/destinations (list connected accounts)
POST /api/projects/{id}/destinations (link)
DELETE /api/projects/{id}/destinations/{id} (unlink)
Phase 3 - Live Control:
POST /api/projects/{id}/live (start)
DELETE /api/projects/{id}/live (stop)
POST /api/projects/{id}/validate (pre-flight check)
Phase 4 - Scheduling:
POST /api/projects/{id}/schedule
DELETE /api/projects/{id}/schedules
Phase 5 - Advanced:
POST /api/projects/{id}/sync
GET /api/projects/{id}/history
GET /api/projects/{id}/analytics
GET /api/projects/{id}/destinations