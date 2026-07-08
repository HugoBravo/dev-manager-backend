--
-- PostgreSQL database dump
--

\restrict YHRN25vh0IFnMdt0wPhtBGqFezSvWZzMEtOKXAxJUuINqrSIhC5DVHxdeEdide3

-- Dumped from database version 10.23 (Debian 10.23-1.pgdg110+1)
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.projects DROP CONSTRAINT IF EXISTS projects_owner_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_columns DROP CONSTRAINT IF EXISTS kanban_columns_board_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_cards DROP CONSTRAINT IF EXISTS cards_column_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_parent_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_card_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_author_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_attachments DROP CONSTRAINT IF EXISTS card_attachments_uploader_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_attachments DROP CONSTRAINT IF EXISTS card_attachments_card_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_boards DROP CONSTRAINT IF EXISTS boards_project_id_foreign;
DROP INDEX IF EXISTS public.sessions_user_id_index;
DROP INDEX IF EXISTS public.sessions_last_activity_index;
DROP INDEX IF EXISTS public.projects_owner_id_index;
DROP INDEX IF EXISTS public.projects_archived_at_index;
DROP INDEX IF EXISTS public.personal_access_tokens_tokenable_type_tokenable_id_index;
DROP INDEX IF EXISTS public.personal_access_tokens_expires_at_index;
DROP INDEX IF EXISTS public.kanban_columns_board_position_index;
DROP INDEX IF EXISTS public.jobs_queue_index;
DROP INDEX IF EXISTS public.failed_jobs_connection_queue_failed_at_index;
DROP INDEX IF EXISTS public.cards_column_id_position_index;
DROP INDEX IF EXISTS public.cards_column_id_archived_at_position_index;
DROP INDEX IF EXISTS public.card_comments_thread_idx;
DROP INDEX IF EXISTS public.card_attachments_card_id_index;
DROP INDEX IF EXISTS public.cache_locks_expiration_index;
DROP INDEX IF EXISTS public.cache_expiration_index;
DROP INDEX IF EXISTS public.boards_project_id_position_index;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_unique;
ALTER TABLE IF EXISTS ONLY public.sessions DROP CONSTRAINT IF EXISTS sessions_pkey;
ALTER TABLE IF EXISTS ONLY public.projects DROP CONSTRAINT IF EXISTS projects_slug_unique;
ALTER TABLE IF EXISTS ONLY public.projects DROP CONSTRAINT IF EXISTS projects_pkey;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_token_unique;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.migrations DROP CONSTRAINT IF EXISTS migrations_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_columns DROP CONSTRAINT IF EXISTS kanban_columns_pkey;
ALTER TABLE IF EXISTS ONLY public.jobs DROP CONSTRAINT IF EXISTS jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.job_batches DROP CONSTRAINT IF EXISTS job_batches_pkey;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_uuid_unique;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_cards DROP CONSTRAINT IF EXISTS cards_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_attachments DROP CONSTRAINT IF EXISTS card_attachments_pkey;
ALTER TABLE IF EXISTS ONLY public.cache DROP CONSTRAINT IF EXISTS cache_pkey;
ALTER TABLE IF EXISTS ONLY public.cache_locks DROP CONSTRAINT IF EXISTS cache_locks_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_boards DROP CONSTRAINT IF EXISTS boards_pkey;
ALTER TABLE IF EXISTS public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.projects ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.personal_access_tokens ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.migrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_comments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_columns ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_cards ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_boards ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_attachments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.failed_jobs ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.users_id_seq;
DROP TABLE IF EXISTS public.users;
DROP TABLE IF EXISTS public.sessions;
DROP SEQUENCE IF EXISTS public.projects_id_seq;
DROP TABLE IF EXISTS public.projects;
DROP SEQUENCE IF EXISTS public.personal_access_tokens_id_seq;
DROP TABLE IF EXISTS public.personal_access_tokens;
DROP TABLE IF EXISTS public.password_reset_tokens;
DROP SEQUENCE IF EXISTS public.migrations_id_seq;
DROP TABLE IF EXISTS public.migrations;
DROP SEQUENCE IF EXISTS public.kanban_columns_id_seq;
DROP TABLE IF EXISTS public.kanban_columns;
DROP SEQUENCE IF EXISTS public.jobs_id_seq;
DROP TABLE IF EXISTS public.jobs;
DROP TABLE IF EXISTS public.job_batches;
DROP SEQUENCE IF EXISTS public.failed_jobs_id_seq;
DROP TABLE IF EXISTS public.failed_jobs;
DROP SEQUENCE IF EXISTS public.cards_id_seq;
DROP TABLE IF EXISTS public.kanban_cards;
DROP SEQUENCE IF EXISTS public.card_comments_id_seq;
DROP TABLE IF EXISTS public.kanban_comments;
DROP SEQUENCE IF EXISTS public.card_attachments_id_seq;
DROP TABLE IF EXISTS public.kanban_attachments;
DROP TABLE IF EXISTS public.cache_locks;
DROP TABLE IF EXISTS public.cache;
DROP SEQUENCE IF EXISTS public.boards_id_seq;
DROP TABLE IF EXISTS public.kanban_boards;
-- *not* dropping schema, since initdb creates it
--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


SET default_tablespace = '';

--
-- Name: kanban_boards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_boards (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    "position" character varying(255) NOT NULL,
    archived_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: boards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.boards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: boards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.boards_id_seq OWNED BY public.kanban_boards.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: kanban_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_attachments (
    id bigint NOT NULL,
    card_id bigint NOT NULL,
    uploader_id bigint,
    disk character varying(32) DEFAULT 'local'::character varying NOT NULL,
    path character varying(512) NOT NULL,
    original_filename character varying(255) NOT NULL,
    mime character varying(127) NOT NULL,
    size_bytes bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: card_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.card_attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: card_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.card_attachments_id_seq OWNED BY public.kanban_attachments.id;


--
-- Name: kanban_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_comments (
    id bigint NOT NULL,
    card_id bigint NOT NULL,
    author_id bigint,
    parent_id bigint,
    body text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: card_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.card_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: card_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.card_comments_id_seq OWNED BY public.kanban_comments.id;


--
-- Name: kanban_cards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_cards (
    id bigint NOT NULL,
    column_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    body text,
    "position" character varying(255) NOT NULL,
    due_date date,
    archived_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cards_id_seq OWNED BY public.kanban_cards.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: kanban_columns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_columns (
    id bigint NOT NULL,
    board_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    "position" character varying(255) NOT NULL,
    archived_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: kanban_columns_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kanban_columns_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kanban_columns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kanban_columns_id_seq OWNED BY public.kanban_columns.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: projects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projects (
    id bigint NOT NULL,
    owner_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    slug character varying(100),
    archived_at timestamp(0) without time zone
);


--
-- Name: projects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projects_id_seq OWNED BY public.projects.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: kanban_attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_attachments ALTER COLUMN id SET DEFAULT nextval('public.card_attachments_id_seq'::regclass);


--
-- Name: kanban_boards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_boards ALTER COLUMN id SET DEFAULT nextval('public.boards_id_seq'::regclass);


--
-- Name: kanban_cards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_cards ALTER COLUMN id SET DEFAULT nextval('public.cards_id_seq'::regclass);


--
-- Name: kanban_columns id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_columns ALTER COLUMN id SET DEFAULT nextval('public.kanban_columns_id_seq'::regclass);


--
-- Name: kanban_comments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_comments ALTER COLUMN id SET DEFAULT nextval('public.card_comments_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: projects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects ALTER COLUMN id SET DEFAULT nextval('public.projects_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
laravel-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b:timer	i:1783484765;	1783484765
laravel-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b	i:1;	1783484765
laravel-cache-f1f70ec40aaa556905d4a030501c0ba4:timer	i:1783540695;	1783540695
laravel-cache-f1f70ec40aaa556905d4a030501c0ba4	i:6;	1783540695
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: kanban_attachments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_attachments (id, card_id, uploader_id, disk, path, original_filename, mime, size_bytes, created_at, updated_at) FROM stdin;
1	1	\N	local	kanban/cards/1/47679ee4-980e-4c45-83cb-57ce406e7358.png	sample.png	image/png	94	2026-07-08 01:18:28	2026-07-08 01:18:28
\.


--
-- Data for Name: kanban_boards; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_boards (id, project_id, name, "position", archived_at, created_at, updated_at) FROM stdin;
1	1	Demo Board	m	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
\.


--
-- Data for Name: kanban_cards; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_cards (id, column_id, title, body, "position", due_date, archived_at, created_at, updated_at) FROM stdin;
1	1	Set up project skeleton	Auto-seeded card: Set up project skeleton.	na	\N	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
2	1	Define acceptance criteria	Auto-seeded card: Define acceptance criteria.	naa	\N	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
3	1	Draft API contract	Auto-seeded card: Draft API contract.	naaa	\N	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
4	2	Implement kanban board UI	Auto-seeded card: Implement kanban board UI.	na	\N	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
5	3	Initial commit	Auto-seeded card: Initial commit.	na	\N	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
\.


--
-- Data for Name: kanban_columns; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_columns (id, board_id, name, "position", archived_at, created_at, updated_at) FROM stdin;
1	1	Backlog	m	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
2	1	In Progress	ma	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
3	1	Done	maa	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
\.


--
-- Data for Name: kanban_comments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_comments (id, card_id, author_id, parent_id, body, created_at, updated_at) FROM stdin;
1	1	6	\N	First root comment from demo user.	2026-07-08 01:18:28	2026-07-08 01:18:28
2	1	6	1	Self-reply under my own root.	2026-07-08 01:18:28	2026-07-08 01:18:28
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_07_04_024301_create_personal_access_tokens_table	1
5	2026_07_07_003229_create_projects_table	2
6	2026_07_07_010000_create_boards_table	2
7	2026_07_07_011928_create_kanban_columns_table	2
8	2026_07_07_015000_create_cards_table	2
9	2026_07_07_020000_add_slug_and_archived_at_to_projects_table	2
10	2026_07_07_030000_create_card_comments_table	2
11	2026_07_07_040000_create_card_attachments_table	2
12	2026_07_07_050001_rename_boards_to_kanban_boards	2
13	2026_07_07_050002_rename_cards_to_kanban_cards	2
14	2026_07_07_050003_rename_card_comments_to_kanban_comments	2
15	2026_07_07_050004_rename_card_attachments_to_kanban_attachments	2
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
1	App\\Models\\User	1	dev-manager-desk:browser	7f9d257b173bb7f1ab3e940649f2b7ccff532832e9f9c5c6f073608704ccf45a	["*"]	\N	\N	2026-07-06 23:53:20	2026-07-06 23:53:20
3	App\\Models\\User	1	bruno-cli	b9e8ad454d69584d94a3c6fa7e5e67dd9f0f6a12ab63d484e65b8064054735eb	["*"]	2026-07-08 01:17:31	\N	2026-07-08 01:15:03	2026-07-08 01:17:31
4	App\\Models\\User	6	demo-token	ed61fd52b2f453325981835d64e94c759f6debcaa9902949c68f7b4cb6b1b53b	["*"]	\N	\N	2026-07-08 01:18:28	2026-07-08 01:18:28
10	App\\Models\\User	1	unknown	f5789b49dc505100c30b4e9f6a82c041853b14d09141dd2b5e7f6955d41d8b58	["*"]	2026-07-08 04:09:37	\N	2026-07-08 04:03:17	2026-07-08 04:09:37
6	App\\Models\\User	1	dev	bc31e8922966dc569979b3082fbf323ee9ffd12aeaedbdf4c1a3c1b2c3ddefb2	["*"]	2026-07-08 01:53:18	\N	2026-07-08 01:53:17	2026-07-08 01:53:18
7	App\\Models\\User	1	dev	b01cf3de2fe87fe441aed413b16eeb0690ef8dfe7c782d2d3921358f67d3231d	["*"]	2026-07-08 01:54:10	\N	2026-07-08 01:54:10	2026-07-08 01:54:10
8	App\\Models\\User	1	dev	3a71b6a24aed6d58363c7b240ceab9f33c0b4bbb97fb0c408580edc660ca4199	["*"]	2026-07-08 01:54:24	\N	2026-07-08 01:54:22	2026-07-08 01:54:24
11	App\\Models\\User	1	dev-manager-desk:browser	6d8b62c051edddc7b8143e60a04d7e4e226534a8abadb820e5e52c3ab25691d7	["*"]	2026-07-08 04:20:25	\N	2026-07-08 04:08:44	2026-07-08 04:20:25
5	App\\Models\\User	1	dev	6719a5eb94a32b4b8c110dcf0c571190279e70172ae71210db66436e11bd0b95	["*"]	2026-07-08 02:04:18	\N	2026-07-08 01:18:40	2026-07-08 02:04:18
12	App\\Models\\User	1	dev-manager-desk:browser	8aefc83bc5c2942f88c97dbfa887be8d8583a2b6409c50e6ac9b0b15e87f7574	["*"]	2026-07-08 04:24:34	\N	2026-07-08 04:22:08	2026-07-08 04:24:34
2	App\\Models\\User	1	dev-manager-desk:browser	353cecd0b4f8cd6e2a6f708a20e3fc0717288463c721dc4ac6f45e3a8961b15f	["*"]	2026-07-08 03:56:48	\N	2026-07-06 23:53:59	2026-07-08 03:56:48
13	App\\Models\\User	1	dev-manager-desk:browser	75640f6fe54be8b1ab34ff9f9338b11f97fb395a01b5e432693c433cae3eb00c	["*"]	2026-07-08 19:57:16	\N	2026-07-08 04:25:06	2026-07-08 19:57:16
9	App\\Models\\User	1	dev-manager-desk:browser	cdbb02962b2deddc3d626fe39ab71b456cc93c0aeb347a04f6b15d97086a53e1	["*"]	2026-07-08 04:08:32	\N	2026-07-08 03:57:53	2026-07-08 04:08:32
\.


--
-- Data for Name: projects; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.projects (id, owner_id, name, description, created_at, updated_at, slug, archived_at) FROM stdin;
1	1	Demo Kanban Project	A pre-populated kanban project for the dev-manager demo.	2026-07-08 01:18:28	2026-07-08 01:20:54	demo-kanban-project	\N
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at) FROM stdin;
1	Hugo Test	hugo@llamadev.cl	2026-07-06 23:52:53	$2y$12$X9rnHTY1bnOkKEUo7xqlCezgRTt.y80Zdc2O79pfa5rGYGiTNvdOy	nERRrBNId2	2026-07-06 23:52:54	2026-07-06 23:52:54
2	Noah Russel	dconnelly@example.com	2026-07-07 01:29:25	$2y$12$GsBtao9A30edej0OaGICCe.7f8risxOiqeV13PMQFUvit8mbEYkwS	46znguvEJP	2026-07-07 01:29:25	2026-07-07 01:29:25
3	Henry Green	madie30@example.com	2026-07-07 01:29:47	$2y$12$mvPUQMCwnzUUZuYQSYd5q.AwyXoRyuLtn4qO12cmRBrGCKWrEq/A2	tKt6sBpKcR	2026-07-07 01:29:47	2026-07-07 01:29:47
4	Jalon Schulist V	walsh.vito@example.com	2026-07-07 01:50:45	$2y$12$A2tGTGSqYccy.nAabVmdquw0dcN3ClUieoCnjvvA9gNmBTLdQwW0u	lD6ANcL2zm	2026-07-07 01:50:47	2026-07-07 01:50:47
5	Arthur Rohan	maiya.runolfsson@example.com	2026-07-07 01:51:41	$2y$12$6IbYzTca/6MgXC0BrJAw3uAj9yrogRiSW6iT6ZOtcHUOW4C0CMpZa	ltl1y5BUe0	2026-07-07 01:51:42	2026-07-07 01:51:42
6	Demo User	demo@dev-manager.test	2026-07-08 01:18:27	$2y$12$rfvJkTco/ib4uvL6scvKdOhUn6inguG6Gvr6rMI.gfF5Qu6dZhEEK	\N	2026-07-08 01:18:27	2026-07-08 01:18:27
\.


--
-- Name: boards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.boards_id_seq', 2, true);


--
-- Name: card_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.card_attachments_id_seq', 1, true);


--
-- Name: card_comments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.card_comments_id_seq', 2, true);


--
-- Name: cards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cards_id_seq', 5, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: kanban_columns_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.kanban_columns_id_seq', 3, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 15, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 13, true);


--
-- Name: projects_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.projects_id_seq', 1, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 6, true);


--
-- Name: kanban_boards boards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_boards
    ADD CONSTRAINT boards_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: kanban_attachments card_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_attachments
    ADD CONSTRAINT card_attachments_pkey PRIMARY KEY (id);


--
-- Name: kanban_comments card_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_comments
    ADD CONSTRAINT card_comments_pkey PRIMARY KEY (id);


--
-- Name: kanban_cards cards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_cards
    ADD CONSTRAINT cards_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: kanban_columns kanban_columns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_columns
    ADD CONSTRAINT kanban_columns_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (id);


--
-- Name: projects projects_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_slug_unique UNIQUE (slug);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: boards_project_id_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX boards_project_id_position_index ON public.kanban_boards USING btree (project_id, "position");


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: card_attachments_card_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX card_attachments_card_id_index ON public.kanban_attachments USING btree (card_id);


--
-- Name: card_comments_thread_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX card_comments_thread_idx ON public.kanban_comments USING btree (card_id, author_id, parent_id);


--
-- Name: cards_column_id_archived_at_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cards_column_id_archived_at_position_index ON public.kanban_cards USING btree (column_id, archived_at, "position");


--
-- Name: cards_column_id_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cards_column_id_position_index ON public.kanban_cards USING btree (column_id, "position");


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: kanban_columns_board_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kanban_columns_board_position_index ON public.kanban_columns USING btree (board_id, "position");


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: projects_archived_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_archived_at_index ON public.projects USING btree (archived_at);


--
-- Name: projects_owner_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_owner_id_index ON public.projects USING btree (owner_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: kanban_boards boards_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_boards
    ADD CONSTRAINT boards_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: kanban_attachments card_attachments_card_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_attachments
    ADD CONSTRAINT card_attachments_card_id_foreign FOREIGN KEY (card_id) REFERENCES public.kanban_cards(id) ON DELETE CASCADE;


--
-- Name: kanban_attachments card_attachments_uploader_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_attachments
    ADD CONSTRAINT card_attachments_uploader_id_foreign FOREIGN KEY (uploader_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: kanban_comments card_comments_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_comments
    ADD CONSTRAINT card_comments_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: kanban_comments card_comments_card_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_comments
    ADD CONSTRAINT card_comments_card_id_foreign FOREIGN KEY (card_id) REFERENCES public.kanban_cards(id) ON DELETE CASCADE;


--
-- Name: kanban_comments card_comments_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_comments
    ADD CONSTRAINT card_comments_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.kanban_comments(id) ON DELETE SET NULL;


--
-- Name: kanban_cards cards_column_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_cards
    ADD CONSTRAINT cards_column_id_foreign FOREIGN KEY (column_id) REFERENCES public.kanban_columns(id) ON DELETE CASCADE;


--
-- Name: kanban_columns kanban_columns_board_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_columns
    ADD CONSTRAINT kanban_columns_board_id_foreign FOREIGN KEY (board_id) REFERENCES public.kanban_boards(id) ON DELETE CASCADE;


--
-- Name: projects projects_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: -
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

\unrestrict YHRN25vh0IFnMdt0wPhtBGqFezSvWZzMEtOKXAxJUuINqrSIhC5DVHxdeEdide3

