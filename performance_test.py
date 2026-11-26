import argparse
import threading
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

import requests

DEFAULT_URL = "http://127.0.0.1:8000/api/getting"

thread_local = threading.local()


def get_session() -> requests.Session:
    if not hasattr(thread_local, "session"):
        thread_local.session = requests.Session()
    return thread_local.session


def send_request(url: str, method: str, headers: dict, payload: dict | None):
    session = get_session()
    request_start = time.perf_counter()
    try:
        if method == "POST":
            response = session.post(url, json=payload, headers=headers, timeout=15)
        else:
            response = session.get(url, headers=headers, timeout=15)

        elapsed_ms = (time.perf_counter() - request_start) * 1000
        return response.status_code, elapsed_ms, None
    except Exception as exc:
        elapsed_ms = (time.perf_counter() - request_start) * 1000
        return None, elapsed_ms, str(exc)


def percentile(sorted_values: list[float], pct: float) -> float:
    if not sorted_values:
        return 0.0
    idx = min(int(len(sorted_values) * pct / 100), len(sorted_values) - 1)
    return sorted_values[idx]


def main():
    parser = argparse.ArgumentParser(description="High-volume API logging load test")
    parser.add_argument("--url", default=DEFAULT_URL, help="Target API URL")
    parser.add_argument("--method", choices=["GET", "POST"], default="GET")
    parser.add_argument("--rpm", type=int, default=4000, help="Target requests per minute")
    parser.add_argument("--duration", type=int, default=60, help="Test duration in seconds")
    parser.add_argument("--workers", type=int, default=32, help="Concurrent worker threads")
    args = parser.parse_args()

    total_requests = max(1, int(args.rpm * args.duration / 60))
    interval = args.duration / total_requests
    headers = {"Accept": "application/json"}
    payload = None

    print(
        f"Starting load test: {total_requests} requests over {args.duration}s "
        f"(target ~{args.rpm} req/min, {args.workers} workers) -> {args.url}"
    )

    success = 0
    failed = 0
    response_times: list[float] = []
    start = time.perf_counter()

    with ThreadPoolExecutor(max_workers=args.workers) as executor:
        futures = []
        for i in range(total_requests):
            target_time = start + (i * interval)
            sleep_for = target_time - time.perf_counter()
            if sleep_for > 0:
                time.sleep(sleep_for)
            futures.append(executor.submit(send_request, args.url, args.method, headers, payload))

        for future in as_completed(futures):
            status_code, elapsed_ms, err = future.result()
            if status_code is not None and 200 <= status_code < 400:
                success += 1
                response_times.append(elapsed_ms)
            else:
                failed += 1
                if err and failed <= 5:
                    print(f"Error: {err}")

    total_duration = time.perf_counter() - start
    actual_rpm = (success / total_duration) * 60 if total_duration > 0 else 0
    sorted_times = sorted(response_times)

    print("\n========== SUMMARY ==========")
    print(f"Total Requests : {total_requests}")
    print(f"Successful     : {success}")
    print(f"Failed         : {failed}")
    if sorted_times:
        print(f"Average Time   : {sum(sorted_times) / len(sorted_times):.2f} ms")
        print(f"p50 Time       : {percentile(sorted_times, 50):.2f} ms")
        print(f"p95 Time       : {percentile(sorted_times, 95):.2f} ms")
        print(f"Min / Max Time : {sorted_times[0]:.2f} ms / {sorted_times[-1]:.2f} ms")
    print(f"Total Duration : {total_duration:.2f} sec")
    print(f"Throughput     : {actual_rpm:.1f} req/min")


if __name__ == "__main__":
    main()