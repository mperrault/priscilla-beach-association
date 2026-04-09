insert into public."Household" (
  pb_street_number,
  pb_street_name,
  household_admin_first_name,
  household_admin_last_name,
  household_admin_email_address,
  correspondence_address,
  invite_policy,
  notes,
  household_status,
  last_modified_at,
  owner_name_raw,
  owner_address_text,
  building_value,
  land_value,
  other_value,
  total_value,
  assessment_fy,
  lot_size_acres,
  last_sale_price,
  last_sale_date,
  use_code,
  year_built,
  residential_area_sqft,
  building_style,
  number_of_units,
  number_of_rooms,
  assessor_book_raw,
  assessor_page_raw,
  property_id,
  location_id,
  owner_occupied,
  parcel_source,
  parcel_last_updated_at
)
select
  nullif(trim(s.pb_street_number), '')::varchar,
  coalesce(nullif(trim(s.pb_street_name), ''), '')::varchar,
  ''::varchar as household_admin_first_name,
  ''::varchar as household_admin_last_name,
  ''::varchar as household_admin_email_address,
  null::text as correspondence_address,
  null::integer as invite_policy,
  null::varchar as notes,
  'Active'::public."HouseholdStatus" as household_status,
  now() as last_modified_at,
  nullif(trim(s.owner_name_raw), '')::varchar,
  nullif(trim(s.owner_address_text), '')::text,

  case
    when nullif(trim(s.building_value), '') is null then null
    else trim(s.building_value)::numeric(12,2)
  end as building_value,

  case
    when nullif(trim(s.land_value), '') is null then null
    else trim(s.land_value)::numeric(12,2)
  end as land_value,

  case
    when nullif(trim(s.other_value), '') is null then null
    else trim(s.other_value)::numeric(12,2)
  end as other_value,

  case
    when nullif(trim(s.total_value), '') is null then null
    else trim(s.total_value)::numeric(12,2)
  end as total_value,

  case
    when nullif(trim(s.assessment_fy), '') is null then null
    else trim(s.assessment_fy)::integer
  end as assessment_fy,

  case
    when nullif(trim(s.lot_size_acres), '') is null then null
    else trim(s.lot_size_acres)::numeric(10,4)
  end as lot_size_acres,

  case
    when nullif(trim(s.last_sale_price), '') is null then null
    else trim(s.last_sale_price)::numeric(12,2)
  end as last_sale_price,

  case
    when nullif(trim(s.last_sale_date), '') is null then null
    else trim(s.last_sale_date)::date
  end as last_sale_date,

  nullif(trim(s.use_code), '')::varchar,

  case
    when nullif(trim(s.year_built), '') is null then null
    else trim(s.year_built)::integer
  end as year_built,

  case
    when nullif(trim(s.residential_area_sqft), '') is null then null
    else trim(s.residential_area_sqft)::integer
  end as residential_area_sqft,

  nullif(trim(s.building_style), '')::varchar,

  case
    when nullif(trim(s.number_of_units), '') is null then null
    else trim(s.number_of_units)::integer
  end as number_of_units,

  case
    when nullif(trim(s.number_of_rooms), '') is null then null
    else trim(s.number_of_rooms)::numeric(5,2)
  end as number_of_rooms,

  nullif(trim(s.assessor_book_raw), '')::varchar,
  nullif(trim(s.assessor_page_raw), '')::varchar,
  nullif(trim(s.property_id), '')::varchar,
  nullif(trim(s.location_id), '')::varchar,

  case lower(trim(coalesce(s.owner_occupied, '')))
    when 'true' then true
    when 'false' then false
    else null
  end as owner_occupied,

  nullif(trim(s.parcel_source), '')::varchar,
  now() as parcel_last_updated_at
from public."Household_Parcel_Staging" s
where nullif(trim(s.property_id), '') is not null
on conflict (property_id)
do update set
  pb_street_number           = excluded.pb_street_number,
  pb_street_name             = excluded.pb_street_name,
  owner_name_raw             = excluded.owner_name_raw,
  owner_address_text         = excluded.owner_address_text,
  building_value             = excluded.building_value,
  land_value                 = excluded.land_value,
  other_value                = excluded.other_value,
  total_value                = excluded.total_value,
  assessment_fy              = excluded.assessment_fy,
  lot_size_acres             = excluded.lot_size_acres,
  last_sale_price            = excluded.last_sale_price,
  last_sale_date             = excluded.last_sale_date,
  use_code                   = excluded.use_code,
  year_built                 = excluded.year_built,
  residential_area_sqft      = excluded.residential_area_sqft,
  building_style             = excluded.building_style,
  number_of_units            = excluded.number_of_units,
  number_of_rooms            = excluded.number_of_rooms,
  assessor_book_raw          = excluded.assessor_book_raw,
  assessor_page_raw          = excluded.assessor_page_raw,
  location_id                = excluded.location_id,
  owner_occupied             = excluded.owner_occupied,
  parcel_source              = excluded.parcel_source,
  parcel_last_updated_at     = excluded.parcel_last_updated_at,
  last_modified_at           = now();

