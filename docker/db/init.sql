DO
$$
BEGIN
   IF NOT EXISTS (
      SELECT FROM pg_database WHERE datname = 'okk_service'
   ) THEN
      CREATE DATABASE okk_service;
END IF;
END
$$;
