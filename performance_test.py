import requests
import time

URL = "http://127.0.0.1:8000/api/login"

payload = {
    "email": "kishore@gmail.com",
    "password": "123456"
}

headers = {
    "Accept": "application/json"
}

total_requests = 50
success = 0
failed = 0
response_times = []

start = time.time()

for i in range(total_requests):

    request_start = time.time()

    try:
        response = requests.post(URL, json=payload, headers=headers)

        elapsed = (time.time() - request_start) * 1000
        response_times.append(elapsed)

        if response.status_code == 200:
            success += 1
        else:
            failed += 1

        print(f"Request {i+1}: {response.status_code} | {elapsed:.2f} ms")

    except Exception as e:
        failed += 1
        print(e)

end = time.time()

print("\n========== SUMMARY ==========")
print(f"Total Requests : {total_requests}")
print(f"Successful     : {success}")
print(f"Failed         : {failed}")
print(f"Average Time   : {sum(response_times)/len(response_times):.2f} ms")
print(f"Minimum Time   : {min(response_times):.2f} ms")
print(f"Maximum Time   : {max(response_times):.2f} ms")
print(f"Total Duration : {(end-start):.2f} sec")