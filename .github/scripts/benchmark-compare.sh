#!/usr/bin/env bash
##
# Compares benchmark timings of two checkouts on a single machine.
#
# Measures the base checkout, then the head checkout, then asserts that no
# subject got slower than the threshold. Both measurements run back to back in
# one process on one host, so the machine-to-machine variance that dwarfs a
# few percent of real change cancels out.
#
# The default threshold sits above the drift measured between two runs of
# identical code on one host, which reaches about 10% on the shortest
# subjects. A real regression is an order of magnitude larger.
#
# Usage:
#   benchmark-compare.sh --base=DIR [--head=DIR] [--threshold=PCT] [-- ARGS]
#
# Arguments after '--' are passed through to the head PHPBench run.
#

set -euo pipefail

# PHPBench exits 2 when an assertion fails, so a malformed invocation reports
# the sysexits usage code to stay distinguishable from a regression.
readonly EXIT_USAGE=64

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root_dir="$(cd "${script_dir}/../.." && pwd)"

base_dir=""
head_dir="${root_dir}"
threshold="15"

while [ "$#" -gt 0 ]; do
  case "$1" in
    --base=*) base_dir="${1#*=}" ;;
    --head=*) head_dir="${1#*=}" ;;
    --threshold=*) threshold="${1#*=}" ;;
    --) shift; break ;;
    *) echo "Unknown argument: $1" >&2; exit "${EXIT_USAGE}" ;;
  esac
  shift
done

passthrough=("$@")

[ -n "${base_dir}" ] || { echo "Missing required --base=DIR." >&2; exit "${EXIT_USAGE}"; }
[ -d "${base_dir}" ] || { echo "Base directory does not exist: ${base_dir}" >&2; exit "${EXIT_USAGE}"; }
[ -d "${head_dir}" ] || { echo "Head directory does not exist: ${head_dir}" >&2; exit "${EXIT_USAGE}"; }

phpbench="${root_dir}/vendor/bin/phpbench"
[ -x "${phpbench}" ] || { echo "PHPBench is not installed at ${phpbench}." >&2; exit "${EXIT_USAGE}"; }

base_dir="$(cd "${base_dir}" && pwd)"
head_dir="$(cd "${head_dir}" && pwd)"

# A stored run is keyed by tag, so a leftover baseline from an earlier
# comparison would be picked up instead of the one measured below.
rm -rf "${base_dir}/.phpbench" "${head_dir}/.phpbench"

echo "==> Measuring base: ${base_dir}"
(cd "${base_dir}" && "${phpbench}" run --store --tag=baseline --progress=none)

cp -R "${base_dir}/.phpbench" "${head_dir}/.phpbench"

echo "==> Measuring head: ${head_dir}"
(cd "${head_dir}" && "${phpbench}" run \
  --ref=baseline \
  --report=aggregate \
  --assert="mode(variant.time.avg) <= mode(baseline.time.avg) +/- ${threshold}%" \
  ${passthrough[@]+"${passthrough[@]}"})
