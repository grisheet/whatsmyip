#!/usr/bin/env python3
"""Build the Pages lookup shards from the two published ipapi.is CSV ZIPs."""

import argparse
import csv
import gzip
from heapq import heappop, heappush
import io
from ipaddress import IPv4Address, IPv6Address
import json
from pathlib import Path
from zipfile import ZipFile

FIELDS = ("country", "state", "city", "zip", "timezone", "latitude", "longitude")
LICENSE_NAME = "LICENSE-free-geolocation-database.txt"


def most_specific(rows):
    """Turn nested, start-sorted ranges into disjoint intervals."""
    active = []
    cursor = 0

    def advance(stop):
        nonlocal cursor
        while cursor <= stop and active:
            size, order, end, fields = active[0]
            if end < cursor:
                heappop(active)
                continue
            last = min(stop, end)
            yield cursor, last, fields
            cursor = last + 1
        cursor = stop + 1

    for order, (start, end, fields) in enumerate(rows):
        if start < cursor or end < start:
            raise ValueError(f"Unsorted or invalid interval at data row {order + 1}")
        yield from advance(start - 1)
        heappush(active, (end - start, order, end, fields))
    if active:
        yield from advance(max(interval[2] for interval in active))


def read_ranges(archive, version):
    address = IPv4Address if version == 4 else IPv6Address
    with archive.open(f"geolocationDatabaseIPv{version}.csv") as source:
        rows = csv.reader(io.TextIOWrapper(source, encoding="utf-8-sig", newline=""))
        headers = next(rows)
        indices = [headers.index(field) for field in ("start_ip", "end_ip", *FIELDS)]
        for row in rows:
            start, end, *fields = (row[index] for index in indices)
            yield int(address(start)), int(address(end)), tuple(fields)


def write_shards(ranges, version, output):
    host_bits = {4: 22, 6: 116}[version]
    prefix_digits = 3
    bucket = None
    locations = {}
    intervals = []

    def flush():
        if intervals:
            filename = f"{version}-{bucket:0{prefix_digits}x}.bin"
            data = json.dumps([list(locations), intervals], ensure_ascii=False, separators=(",", ":"))
            (output / filename).write_bytes(gzip.compress(data.encode(), compresslevel=6, mtime=0))

    for start, end, fields in ranges:
        while start <= end:
            prefix = start >> host_bits
            base = prefix << host_bits
            last = min(end, base + (1 << host_bits) - 1)
            if prefix != bucket:
                flush()
                bucket = prefix
                locations = {}
                intervals = []

            if any(fields):
                location = locations.setdefault(fields, len(locations))
                first_offset, last_offset = start - base, last - base
                if intervals and intervals[-1][2] == location and int(intervals[-1][1], 16) + 1 == first_offset:
                    intervals[-1][1] = format(last_offset, "x")
                else:
                    intervals.append([format(first_offset, "x"), format(last_offset, "x"), location])
            start = last + 1
    flush()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("ipv4", type=Path)
    parser.add_argument("ipv6", type=Path)
    parser.add_argument("output", type=Path, help="New or empty output directory")
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    if any(args.output.iterdir()):
        parser.error("output directory must be empty")

    for version, file in ((4, args.ipv4), (6, args.ipv6)):
        with ZipFile(file) as archive:
            (args.output / LICENSE_NAME).write_bytes(archive.read(LICENSE_NAME))
            write_shards(most_specific(read_ranges(archive, version)), version, args.output)
        print(f"Built IPv{version} shards", flush=True)

    shard_names = sorted(file.stem for file in args.output.glob("*.bin"))
    (args.output / "index.json").write_text(json.dumps(shard_names, separators=(",", ":")) + "\n")


if __name__ == "__main__":
    main()
