# VPS database audit — 20 October 2026

Read-only comparison of the retained original Laravel deployment and the upgraded review deployment. No account permissions, school links or records were changed.

| Records | Original database | Upgraded review database |
|---|---:|---:|
| Users, including soft-deleted | 8 | 16 |
| Schools | 2 | 4 |
| Student rows, including soft-deleted | 2 | 5 |
| Classes / class sections | 1 / 1 | 2 / 2 |
| Legacy parent profiles | 4 | 4 |
| Legacy teacher profiles | 1 | 1 |
| Legacy user-school links | 3 | 3 |
| Legacy meetings / main meetings | 0 / 0 | 0 / 0 |
| Legacy attendance / assignments / exam results / SQL chat | 0 each | 0 each |

The upgrade added five review portal accounts, three fictional student accounts, two review schools and their sample activity. All five new workspace memberships belong to the explicitly provisioned review accounts. New workspace data contains three student updates, one message, one appointment, three posts and one verified guardian link. Existing legacy user-school links are not automatically trusted as approved new workspace memberships.

The two pre-existing student rows both have no email on their linked user account and no valid class-section/school link. One student and its linked user are soft-deleted; the other remains active. The active student therefore cannot be shown through the portal's school/class scope until its legitimate school relationship is established. No relationship was invented or automatically granted.

Correction to the earlier email-based count: `email NOT LIKE '%@workspace.test'` excludes SQL NULL values. Reporting zero existing students from that filter was incorrect. There are two legacy rows, one active and one deleted, with incomplete school relationships. This audit does not establish whether those records came from the installed phone app or earlier testing.

The two deployments use separate MySQL containers, each with a database named `ptm` (81 original tables versus 102 upgraded tables). Data is not continuously replicated between the retained rollback database and the active review database. The phone app's actual API origin has not been supplied or verified. Firebase message history was not inspected by this SQL audit; an empty SQL chat table does not prove Firebase has no chats.
