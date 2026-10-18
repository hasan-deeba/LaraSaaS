import {
  pgTable,
  serial,
  integer,
  text,
  boolean,
  timestamp,
  jsonb,
  uniqueIndex,
  index,
} from "drizzle-orm/pg-core";

/**
 * Demo schema for the live showcase — it mirrors the exact tables the
 * Laravel package ships (same names, same semantics), so the announcements
 * demo below exercises the real backend logic: scheduling windows,
 * active flags and per-visitor dismissals.
 */

export const larasaasAnnouncements = pgTable(
  "larasaas_announcements",
  {
    id: serial("id").primaryKey(),
    title: text("title").notNull(),
    body: text("body").notNull(),
    type: text("type").notNull().default("info"),
    color: text("color"),
    icon: text("icon"),
    actionLabel: text("action_label"),
    actionUrl: text("action_url"),
    dismissible: boolean("dismissible").notNull().default(true),
    active: boolean("active").notNull().default(true),
    audience: jsonb("audience"),
    startsAt: timestamp("starts_at", { withTimezone: true }),
    endsAt: timestamp("ends_at", { withTimezone: true }),
    createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(),
  },
  (table) => [index("ls_ann_window_idx").on(table.active, table.startsAt, table.endsAt)],
);

export const larasaasAnnouncementDismissals = pgTable(
  "larasaas_announcement_dismissals",
  {
    id: serial("id").primaryKey(),
    announcementId: integer("announcement_id")
      .notNull()
      .references(() => larasaasAnnouncements.id, { onDelete: "cascade" }),
    visitorId: text("visitor_id").notNull(),
    createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  },
  (table) => [uniqueIndex("ls_ann_dismissal_unique").on(table.announcementId, table.visitorId)],
);

/** Shareable pricing presets saved from the playground. */
export const larasaasPricingPresets = pgTable(
  "larasaas_pricing_presets",
  {
    id: serial("id").primaryKey(),
    slug: text("slug").notNull(),
    name: text("name"),
    config: jsonb("config").notNull(),
    createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  },
  (table) => [uniqueIndex("ls_preset_slug_unique").on(table.slug)],
);
