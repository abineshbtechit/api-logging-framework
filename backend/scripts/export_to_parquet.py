import json
import sys
import pyarrow as pa
import pyarrow.parquet as pq


def main():
    if len(sys.argv) != 3:
        print("Usage: export_to_parquet.py <input.jsonl> <output.parquet>", file=sys.stderr)
        sys.exit(1)

    input_path, output_path = sys.argv[1], sys.argv[2]

    rows = []
    with open(input_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                rows.append(json.loads(line))

    if not rows:
        print("No rows to export.", file=sys.stderr)
        sys.exit(1)

    schema = pa.schema([
        ("id", pa.int64()),
        ("user_id", pa.int64()),
        ("user_role", pa.string()),
        ("method", pa.string()),
        ("endpoint", pa.string()),
        ("request_headers", pa.string()),
        ("request_body", pa.string()),
        ("response_body", pa.string()),
        ("status_code", pa.int32()),
        ("response_time", pa.float64()),
        ("ip_address", pa.string()),
        ("user_agent", pa.string()),
        ("created_at", pa.string()),
        ("updated_at", pa.string()),
    ])

    table = pa.Table.from_pylist(rows, schema=schema)
    pq.write_table(table, output_path, compression="zstd")


if __name__ == "__main__":
    main()
