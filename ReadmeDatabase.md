# Priscilla Beach Association Database Schema

The PBA WordPress site utilizes a Postgresql backend database hosted by Supabase: https://supabase.com/

A free account has been established during the prototyping phase of this project under the mperrault@milestonesw.com account. If it is desirable to move forward from prototype to production, this database structure will be re-created in a PBA specific account.

## Database schema

The following script can be used to create the database schema used by the PBA website prototype:

```
-- WARNING: This schema is for context only and is not meant to be run.
-- Table order and constraints may not be valid for execution.

CREATE TABLE public.Household (
  household_id bigint GENERATED ALWAYS AS IDENTITY NOT NULL,
  created_at timestamp with time zone NOT NULL DEFAULT now(),
  pb_street_number character varying,
  pb_street_name character varying NOT NULL DEFAULT ''::character varying,
  household_admin_first_name character varying NOT NULL DEFAULT ''::character varying,
  household_admin_last_name character varying NOT NULL DEFAULT ''::character varying,
  household_admin_email_address character varying NOT NULL,
  correspondence_address text,
  invite_policy integer,
  notes character varying,
  household_status USER-DEFINED DEFAULT 'Active'::"HouseholdStatus",
  CONSTRAINT Household_pkey PRIMARY KEY (household_id)
);
CREATE TABLE public.Person (
  person_id bigint GENERATED ALWAYS AS IDENTITY NOT NULL,
  created_at timestamp with time zone NOT NULL DEFAULT now(),
  last_modified_at timestamp with time zone NOT NULL DEFAULT now(),
  household_id bigint NOT NULL,
  first_name character varying,
  last_name character varying,
  email_address character varying,
  status USER-DEFINED,
  invited_by_person_id bigint,
  email_verified smallint,
  wp_user_id character varying,
  CONSTRAINT Person_pkey PRIMARY KEY (person_id),
  CONSTRAINT person_household_id_fkey FOREIGN KEY (household_id) REFERENCES public.Household(household_id)
);
CREATE TABLE public.Person_to_Role (
  person_to_role_id bigint GENERATED ALWAYS AS IDENTITY NOT NULL,
  created_at timestamp with time zone NOT NULL DEFAULT now(),
  person_id bigint NOT NULL,
  role_id bigint NOT NULL,
  CONSTRAINT Person_to_Role_pkey PRIMARY KEY (person_to_role_id),
  CONSTRAINT Person_to_Role_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.Role(role_id)
);
CREATE TABLE public.Role (
  role_id bigint GENERATED ALWAYS AS IDENTITY NOT NULL,
  created_at timestamp with time zone NOT NULL DEFAULT now(),
  role_name character varying,
  role_description character varying,
  CONSTRAINT Role_pkey PRIMARY KEY (role_id)
);

```
