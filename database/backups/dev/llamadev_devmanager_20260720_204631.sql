--
-- PostgreSQL database dump
--


-- Dumped from database version 10.23 (Debian 10.23-1.pgdg110+1)
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.secrets DROP CONSTRAINT IF EXISTS secrets_project_id_foreign;
ALTER TABLE IF EXISTS ONLY public.projects DROP CONSTRAINT IF EXISTS projects_owner_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_labels DROP CONSTRAINT IF EXISTS kanban_labels_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_columns DROP CONSTRAINT IF EXISTS kanban_columns_board_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_card_label DROP CONSTRAINT IF EXISTS kanban_card_label_label_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_card_label DROP CONSTRAINT IF EXISTS kanban_card_label_card_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_cards DROP CONSTRAINT IF EXISTS cards_column_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_parent_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_card_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_comments DROP CONSTRAINT IF EXISTS card_comments_author_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_attachments DROP CONSTRAINT IF EXISTS card_attachments_uploader_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_attachments DROP CONSTRAINT IF EXISTS card_attachments_card_id_foreign;
ALTER TABLE IF EXISTS ONLY public.kanban_boards DROP CONSTRAINT IF EXISTS boards_project_id_foreign;
ALTER TABLE IF EXISTS ONLY public.board_audit_logs DROP CONSTRAINT IF EXISTS board_audit_logs_board_id_foreign;
ALTER TABLE IF EXISTS ONLY public.board_audit_logs DROP CONSTRAINT IF EXISTS board_audit_logs_actor_user_id_foreign;
DROP INDEX IF EXISTS public.sessions_user_id_index;
DROP INDEX IF EXISTS public.sessions_last_activity_index;
DROP INDEX IF EXISTS public.secrets_project_id_index;
DROP INDEX IF EXISTS public.projects_owner_id_index;
DROP INDEX IF EXISTS public.projects_archived_at_index;
DROP INDEX IF EXISTS public.personal_access_tokens_tokenable_type_tokenable_id_index;
DROP INDEX IF EXISTS public.personal_access_tokens_expires_at_index;
DROP INDEX IF EXISTS public.kanban_columns_board_position_index;
DROP INDEX IF EXISTS public.kanban_card_label_label_id_index;
DROP INDEX IF EXISTS public.kanban_boards_trash_index;
DROP INDEX IF EXISTS public.kanban_boards_project_name_active_unique;
DROP INDEX IF EXISTS public.jobs_queue_index;
DROP INDEX IF EXISTS public.failed_jobs_connection_queue_failed_at_index;
DROP INDEX IF EXISTS public.cards_column_id_position_index;
DROP INDEX IF EXISTS public.cards_column_id_archived_at_position_index;
DROP INDEX IF EXISTS public.card_comments_thread_idx;
DROP INDEX IF EXISTS public.card_attachments_card_id_index;
DROP INDEX IF EXISTS public.cache_locks_expiration_index;
DROP INDEX IF EXISTS public.cache_expiration_index;
DROP INDEX IF EXISTS public.boards_project_id_position_index;
DROP INDEX IF EXISTS public.board_audit_logs_board_created_index;
DROP INDEX IF EXISTS public.board_audit_logs_action_index;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_unique;
ALTER TABLE IF EXISTS ONLY public.sessions DROP CONSTRAINT IF EXISTS sessions_pkey;
ALTER TABLE IF EXISTS ONLY public.secrets DROP CONSTRAINT IF EXISTS secrets_project_id_key_unique;
ALTER TABLE IF EXISTS ONLY public.secrets DROP CONSTRAINT IF EXISTS secrets_pkey;
ALTER TABLE IF EXISTS ONLY public.projects DROP CONSTRAINT IF EXISTS projects_slug_unique;
ALTER TABLE IF EXISTS ONLY public.projects DROP CONSTRAINT IF EXISTS projects_pkey;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_token_unique;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.migrations DROP CONSTRAINT IF EXISTS migrations_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_labels DROP CONSTRAINT IF EXISTS kanban_labels_user_id_name_unique;
ALTER TABLE IF EXISTS ONLY public.kanban_labels DROP CONSTRAINT IF EXISTS kanban_labels_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_columns DROP CONSTRAINT IF EXISTS kanban_columns_pkey;
ALTER TABLE IF EXISTS ONLY public.kanban_card_label DROP CONSTRAINT IF EXISTS kanban_card_label_pkey;
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
ALTER TABLE IF EXISTS ONLY public.board_audit_logs DROP CONSTRAINT IF EXISTS board_audit_logs_pkey;
ALTER TABLE IF EXISTS public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.secrets ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.projects ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.personal_access_tokens ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.migrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_labels ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_comments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_columns ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_cards ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_boards ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.kanban_attachments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.failed_jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.board_audit_logs ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.users_id_seq;
DROP TABLE IF EXISTS public.users;
DROP TABLE IF EXISTS public.sessions;
DROP SEQUENCE IF EXISTS public.secrets_id_seq;
DROP TABLE IF EXISTS public.secrets;
DROP SEQUENCE IF EXISTS public.projects_id_seq;
DROP TABLE IF EXISTS public.projects;
DROP SEQUENCE IF EXISTS public.personal_access_tokens_id_seq;
DROP TABLE IF EXISTS public.personal_access_tokens;
DROP TABLE IF EXISTS public.password_reset_tokens;
DROP SEQUENCE IF EXISTS public.migrations_id_seq;
DROP TABLE IF EXISTS public.migrations;
DROP SEQUENCE IF EXISTS public.kanban_labels_id_seq;
DROP TABLE IF EXISTS public.kanban_labels;
DROP SEQUENCE IF EXISTS public.kanban_columns_id_seq;
DROP TABLE IF EXISTS public.kanban_columns;
DROP TABLE IF EXISTS public.kanban_card_label;
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
DROP SEQUENCE IF EXISTS public.board_audit_logs_id_seq;
DROP TABLE IF EXISTS public.board_audit_logs;
-- *not* dropping schema, since initdb creates it
--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


SET default_tablespace = '';

--
-- Name: board_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.board_audit_logs (
    id bigint NOT NULL,
    board_id bigint NOT NULL,
    actor_user_id bigint,
    action character varying(50) NOT NULL,
    payload json,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: board_audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.board_audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: board_audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.board_audit_logs_id_seq OWNED BY public.board_audit_logs.id;


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
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
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
-- Name: kanban_card_label; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_card_label (
    card_id bigint NOT NULL,
    label_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


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
-- Name: kanban_labels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kanban_labels (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(64) NOT NULL,
    color character varying(7) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: kanban_labels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kanban_labels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kanban_labels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kanban_labels_id_seq OWNED BY public.kanban_labels.id;


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
-- Name: secrets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.secrets (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    key character varying(100) NOT NULL,
    value text NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: secrets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.secrets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: secrets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.secrets_id_seq OWNED BY public.secrets.id;


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
    updated_at timestamp(0) without time zone,
    is_admin boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone
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
-- Name: board_audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.board_audit_logs ALTER COLUMN id SET DEFAULT nextval('public.board_audit_logs_id_seq'::regclass);


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
-- Name: kanban_labels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_labels ALTER COLUMN id SET DEFAULT nextval('public.kanban_labels_id_seq'::regclass);


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
-- Name: secrets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets ALTER COLUMN id SET DEFAULT nextval('public.secrets_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: board_audit_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.board_audit_logs (id, board_id, actor_user_id, action, payload, created_at) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
laravel-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b:timer	i:1784594665;	1784594665
laravel-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b	i:1;	1784594665
laravel-cache-f1f70ec40aaa556905d4a030501c0ba4:timer	i:1784594836;	1784594837
laravel-cache-f1f70ec40aaa556905d4a030501c0ba4	i:2;	1784594837
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
\.


--
-- Data for Name: kanban_boards; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_boards (id, project_id, name, "position", archived_at, created_at, updated_at, deleted_at) FROM stdin;
3	1	EJEMPLO DE BOARD	x	\N	2026-07-11 22:13:56	2026-07-11 22:13:56	\N
1	1	Demo Board	m	\N	2026-07-11 21:12:09	2026-07-11 23:21:48	2026-07-11 23:21:48
2	1	nuevo boar d	t	\N	2026-07-11 21:43:08	2026-07-11 23:22:00	2026-07-11 23:22:00
6	4	HOSTALBELEN:CL	n	\N	2026-07-14 02:59:56	2026-07-14 02:59:56	\N
7	5	HOSTALCAMPOBASE.CL	n	\N	2026-07-14 18:24:29	2026-07-14 18:24:29	\N
8	8	HOSTEL-MANAGER	n	\N	2026-07-14 22:28:23	2026-07-14 22:28:23	\N
\.


--
-- Data for Name: kanban_card_label; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_card_label (card_id, label_id, created_at, updated_at) FROM stdin;
6	1	2026-07-11 23:08:46	2026-07-11 23:08:46
7	1	2026-07-12 23:21:00	2026-07-12 23:21:00
7	4	2026-07-12 23:21:04	2026-07-12 23:21:04
\.


--
-- Data for Name: kanban_cards; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_cards (id, column_id, title, body, "position", due_date, archived_at, created_at, updated_at) FROM stdin;
6	6	s	s	n	2026-07-10	\N	2026-07-11 23:08:18	2026-07-12 23:19:05
7	4	mmm	# Título Principal (H1)\n## Subtítulo (H2)\n### Sección (H3)	n	\N	\N	2026-07-12 23:20:15	2026-07-12 23:20:15
\.


--
-- Data for Name: kanban_columns; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_columns (id, board_id, name, "position", archived_at, created_at, updated_at) FROM stdin;
4	3	BACKLOG	n	\N	2026-07-11 22:48:26	2026-07-11 22:48:26
5	3	WIP	u	\N	2026-07-11 23:09:02	2026-07-11 23:09:02
6	3	DONE	x	\N	2026-07-11 23:09:09	2026-07-11 23:09:09
9	7	BACKLOG	n	\N	2026-07-14 22:25:01	2026-07-14 22:25:01
10	7	WIP	u	\N	2026-07-14 22:25:06	2026-07-14 22:25:06
11	7	DONE	x	\N	2026-07-14 22:25:12	2026-07-14 22:25:12
12	8	BACKLOG	n	\N	2026-07-14 22:28:33	2026-07-14 22:28:33
13	8	WIP	u	\N	2026-07-14 22:28:40	2026-07-14 22:28:40
14	8	DONE	x	\N	2026-07-14 22:28:45	2026-07-14 22:28:45
\.


--
-- Data for Name: kanban_comments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_comments (id, card_id, author_id, parent_id, body, created_at, updated_at) FROM stdin;
3	7	1	\N	uguguyg	2026-07-12 23:20:36	2026-07-12 23:20:36
\.


--
-- Data for Name: kanban_labels; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.kanban_labels (id, user_id, name, color, created_at, updated_at) FROM stdin;
1	1	IMPORTANT	#ef4444	2026-07-11 22:19:06	2026-07-11 22:19:22
2	1	WARN	#f59e0b	2026-07-11 22:19:36	2026-07-11 22:19:36
4	1	LARAVEL	#8b5cf6	2026-07-11 22:20:24	2026-07-11 22:20:24
5	1	ANGULAR	#ec4899	2026-07-11 22:48:14	2026-07-11 22:48:14
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_07_04_024301_create_personal_access_tokens_table	1
5	2026_07_07_003229_create_projects_table	1
6	2026_07_07_010000_create_boards_table	1
7	2026_07_07_011928_create_kanban_columns_table	1
8	2026_07_07_015000_create_cards_table	1
9	2026_07_07_020000_add_slug_and_archived_at_to_projects_table	1
10	2026_07_07_030000_create_card_comments_table	1
11	2026_07_07_040000_create_card_attachments_table	1
12	2026_07_07_050001_rename_boards_to_kanban_boards	1
13	2026_07_07_050002_rename_cards_to_kanban_cards	1
14	2026_07_07_050003_rename_card_comments_to_kanban_comments	1
15	2026_07_07_050004_rename_card_attachments_to_kanban_attachments	1
16	2026_07_08_221048_create_kanban_labels_table	1
17	2026_07_08_221049_create_kanban_card_label_table	1
18	2026_07_10_212729_add_soft_delete_to_kanban_boards	2
19	2026_07_10_212730_normalise_kanban_positions	2
20	2026_07_11_010001_create_board_audit_logs_table	2
21	2026_07_11_010002_add_unique_index_board_name_active	2
22	2026_07_13_210000_create_secrets_table	3
23	2026_07_14_220000_add_is_admin_and_soft_deletes_to_users_table	4
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
1	App\\Models\\User	1	demo-token	6cefaeaa6a07b6c0159af589903bd17174a859d7e95b5c9d32bd62ce12619f33	["*"]	\N	\N	2026-07-11 21:12:09	2026-07-11 21:12:09
2	App\\Models\\User	1	unknown	32653364787116e86188fcb5be65179c41297a1dc0bfb19579c58eea71991c73	["*"]	\N	\N	2026-07-11 21:30:30	2026-07-11 21:30:30
9	App\\Models\\User	1	dev-manager-desk:browser	120ee06191d53dae9c4ab17e04fa9a710987dfe0b3d7be2b879cc55fcafcda6d	["*"]	2026-07-14 22:28:45	\N	2026-07-14 03:41:27	2026-07-14 22:28:45
13	App\\Models\\User	1	dev-manager-desk:browser	546be2c5321b03df9a23b42d65d6297f2636184f9c17c60ba73a625978a85727	["*"]	2026-07-21 00:46:19	\N	2026-07-21 00:43:26	2026-07-21 00:46:19
10	App\\Models\\User	1	dev-manager-desk:browser	0787ca1cb8ebddcff3297c79015eb5e448fe26bf5656104306fc1a6fe65f8fc9	["*"]	2026-07-18 02:22:49	\N	2026-07-15 13:49:52	2026-07-18 02:22:49
11	App\\Models\\User	1	dev-manager-desk:browser	67ea5c8dee20d79690f527a9e96f49aba1016ae071ba61f9ae4852274fdd1219	["*"]	\N	\N	2026-07-18 02:53:35	2026-07-18 02:53:35
3	App\\Models\\User	1	dev-manager-desk:browser	8c21a23d7f704fcaa4caf25f92b841078ff88d47e79268aaba08f3947f33e3c7	["*"]	2026-07-11 23:56:41	\N	2026-07-11 21:42:26	2026-07-11 23:56:41
4	App\\Models\\User	1	dev-manager-desk:browser	705fe085aa85fa3933e98f62179fdea89a7f557e4e1fc8f42b885b7ba778a2b4	["*"]	2026-07-12 00:03:22	\N	2026-07-11 23:57:11	2026-07-12 00:03:22
5	App\\Models\\User	1	dev-manager-desk:browser	3ce0d2acd82094bd4b51d3ccb49e8c75b2066c8889dcf012fe71ca56457c0dd3	["*"]	2026-07-12 22:23:44	\N	2026-07-12 01:11:20	2026-07-12 22:23:44
12	App\\Models\\User	1	dev-manager-desk:browser	627f4fcbd4a520027270783846cf4c09804e8b4f1cd3207dddd023dd8874432b	["*"]	2026-07-21 00:43:15	\N	2026-07-18 02:53:55	2026-07-21 00:43:15
6	App\\Models\\User	1	dev-manager-desk:browser	0edbc46f84c6718234c7b149d57bf00a2d13df4b20ffeb75203d8a1ded702f24	["*"]	2026-07-12 22:43:08	\N	2026-07-12 22:23:52	2026-07-12 22:43:08
8	App\\Models\\User	1	dev-manager-desk:browser	90c2259b365526cabc89946e6d219fd7ce6a25b171846f371d429658fa766cd0	["*"]	2026-07-14 03:20:18	\N	2026-07-12 23:18:35	2026-07-14 03:20:18
7	App\\Models\\User	1	dev-manager-desk:browser	2a222f627ff511f94a67708f61d91693084a364ed12d96b3e8456a0bec4a000d	["*"]	2026-07-12 23:18:27	\N	2026-07-12 23:16:13	2026-07-12 23:18:27
\.


--
-- Data for Name: projects; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.projects (id, owner_id, name, description, created_at, updated_at, slug, archived_at) FROM stdin;
1	1	Demo Kanban Project	A pre-populated kanban project for the dev-manager demo.	2026-07-11 21:10:55	2026-07-13 16:06:43	demo-kanban-project	2026-07-13 16:06:42
4	1	HOSTAL BELEN	\N	2026-07-14 02:59:40	2026-07-14 02:59:40	hostal-belen	\N
5	1	HOSTAL CAMPO BASE	\N	2026-07-14 03:44:23	2026-07-14 03:44:23	hostal-campo-base	\N
6	1	HOSTAL MAMATIERRA	\N	2026-07-14 03:44:34	2026-07-14 03:44:34	hostal-mamatierra	\N
7	1	DEV-MANAGER	\N	2026-07-14 22:27:01	2026-07-14 22:27:01	dev-manager	\N
8	1	HOSTEL-MANAGER	\N	2026-07-14 22:27:14	2026-07-14 22:27:14	hostel-manager	\N
9	1	TOUR-LOGIC	\N	2026-07-14 22:27:43	2026-07-14 22:27:43	tour-logic	\N
\.


--
-- Data for Name: secrets; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.secrets (id, project_id, key, value, description, created_at, updated_at) FROM stdin;
3	4	gh-cashflow-api@hostalbelen.cl	eyJpdiI6Ii9HK1VEQmE1c3lMcVBKTk8yenhqRlE9PSIsInZhbHVlIjoiMDgrVTExY1ZpTFR2RnFFb0hzd1hSdz09IiwibWFjIjoiNWZkMTUxYzFlNzcxNTAxZTkwZjlkYzEzYjhlZDM5OWExMDk2YTg0ZTc4MTZhNjllMDdjYTM4YjlmMjg4ZWI1NyIsInRhZyI6IiJ9	\N	2026-07-14 14:15:50	2026-07-14 14:15:50
4	4	gh-cashflow@hostalbelen.cl	eyJpdiI6InY0UEl3QWFyZHh4M1pNZ0IzazBxV0E9PSIsInZhbHVlIjoiWVBvZDcwNGJiYlRpTGpuU2hvSWN2UT09IiwibWFjIjoiNWQ5NzBkYjcyZDBmYmM5OGYxYWM5OTZiMmM3NDZiY2MwN2QxMWM3NzM5NmU0M2JhYjcwZDViMzgwODU2NTFhZSIsInRhZyI6IiJ9	\N	2026-07-14 14:16:21	2026-07-14 14:16:21
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, is_admin, deleted_at) FROM stdin;
1	Demo User	demo@dev-manager.test	2026-07-11 21:12:09	$2y$12$BrtA/L9qqCkiru1pgz7UZ.lSxY2Q7iTwKIOQofq6B2BvpOItCnbQ2	\N	2026-07-11 21:10:55	2026-07-11 21:12:09	t	\N
2	Hugo Bravo	hugo@llamadev.cl	\N	$2y$12$IlidgZLqjxtFp2E0dacDI.DIEO.oFPjiCVF/KbYBjX8zPYE1p/4XC	\N	2026-07-21 00:43:50	2026-07-21 00:43:50	t	\N
\.


--
-- Name: board_audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.board_audit_logs_id_seq', 1, false);


--
-- Name: boards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.boards_id_seq', 8, true);


--
-- Name: card_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.card_attachments_id_seq', 1, true);


--
-- Name: card_comments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.card_comments_id_seq', 3, true);


--
-- Name: cards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cards_id_seq', 7, true);


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

SELECT pg_catalog.setval('public.kanban_columns_id_seq', 14, true);


--
-- Name: kanban_labels_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.kanban_labels_id_seq', 5, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 23, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 13, true);


--
-- Name: projects_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.projects_id_seq', 9, true);


--
-- Name: secrets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.secrets_id_seq', 4, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 2, true);


--
-- Name: board_audit_logs board_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.board_audit_logs
    ADD CONSTRAINT board_audit_logs_pkey PRIMARY KEY (id);


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
-- Name: kanban_card_label kanban_card_label_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_card_label
    ADD CONSTRAINT kanban_card_label_pkey PRIMARY KEY (card_id, label_id);


--
-- Name: kanban_columns kanban_columns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_columns
    ADD CONSTRAINT kanban_columns_pkey PRIMARY KEY (id);


--
-- Name: kanban_labels kanban_labels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_labels
    ADD CONSTRAINT kanban_labels_pkey PRIMARY KEY (id);


--
-- Name: kanban_labels kanban_labels_user_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_labels
    ADD CONSTRAINT kanban_labels_user_id_name_unique UNIQUE (user_id, name);


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
-- Name: secrets secrets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets
    ADD CONSTRAINT secrets_pkey PRIMARY KEY (id);


--
-- Name: secrets secrets_project_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets
    ADD CONSTRAINT secrets_project_id_key_unique UNIQUE (project_id, key);


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
-- Name: board_audit_logs_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX board_audit_logs_action_index ON public.board_audit_logs USING btree (action);


--
-- Name: board_audit_logs_board_created_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX board_audit_logs_board_created_index ON public.board_audit_logs USING btree (board_id, created_at);


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
-- Name: kanban_boards_project_name_active_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX kanban_boards_project_name_active_unique ON public.kanban_boards USING btree (project_id, lower((name)::text)) WHERE (deleted_at IS NULL);


--
-- Name: kanban_boards_trash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kanban_boards_trash_index ON public.kanban_boards USING btree (deleted_at, project_id, "position");


--
-- Name: kanban_card_label_label_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kanban_card_label_label_id_index ON public.kanban_card_label USING btree (label_id);


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
-- Name: secrets_project_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX secrets_project_id_index ON public.secrets USING btree (project_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: board_audit_logs board_audit_logs_actor_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.board_audit_logs
    ADD CONSTRAINT board_audit_logs_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: board_audit_logs board_audit_logs_board_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.board_audit_logs
    ADD CONSTRAINT board_audit_logs_board_id_foreign FOREIGN KEY (board_id) REFERENCES public.kanban_boards(id) ON DELETE CASCADE;


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
-- Name: kanban_card_label kanban_card_label_card_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_card_label
    ADD CONSTRAINT kanban_card_label_card_id_foreign FOREIGN KEY (card_id) REFERENCES public.kanban_cards(id) ON DELETE CASCADE;


--
-- Name: kanban_card_label kanban_card_label_label_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_card_label
    ADD CONSTRAINT kanban_card_label_label_id_foreign FOREIGN KEY (label_id) REFERENCES public.kanban_labels(id) ON DELETE CASCADE;


--
-- Name: kanban_columns kanban_columns_board_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_columns
    ADD CONSTRAINT kanban_columns_board_id_foreign FOREIGN KEY (board_id) REFERENCES public.kanban_boards(id) ON DELETE CASCADE;


--
-- Name: kanban_labels kanban_labels_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kanban_labels
    ADD CONSTRAINT kanban_labels_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: projects projects_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: secrets secrets_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets
    ADD CONSTRAINT secrets_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: -
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--


