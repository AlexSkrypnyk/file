#!/usr/bin/env bash
##
# Measures PHPBench subjects in one or two checkouts on a single machine.
#
# With --base, that checkout is measured first and the head checkout is
# asserted against it: no subject may get slower by more than the threshold.
# Both measurements run back to back on one host, so the spread between two
# hosts, which reaches several times the change most subjects are meant to
# detect, cancels out. Nothing is stored between runs.
#
# Without --base, the head checkout is measured on its own and reported.
#
# Path length is part of what is measured: a subject that resolves against
# the working directory pays for every character of it, and a name 20
# characters longer costs about as much as four extra directory levels. Give
# the two checkouts names of the same length.
#
# The default threshold sits above the drift measured between two runs of
# identical code on one host, which reaches about 10% on the shortest
# subjects. A real regression is an order of magnitude larger.
#
# Usage:
#   benchmark-compare.sh [--base=DIR] [--head=DIR] [--threshold=PCT] [-- ARGS]
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
head_dir="$(pwd)"
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

phpbench="${root_dir}/vendor/bin/phpbench"
[ -x "${phpbench}" ] || { echo "PHPBench is not installed at ${phpbench}." >&2; exit "${EXIT_USAGE}"; }

[ -d "${head_dir}" ] || { echo "Head directory does not exist: ${head_dir}" >&2; exit "${EXIT_USAGE}"; }
head_dir="$(cd "${head_dir}" && pwd)"

# A checkout that carries stored results would supply the reference the head
# run resolves, instead of the base measured below.
rm -rf "${head_dir}/.phpbench"

reference=()

if [ -n "${base_dir}" ]; then
  [ -d "${base_dir}" ] || { echo "Base directory does not exist: ${base_dir}" >&2; exit "${EXIT_USAGE}"; }
  base_dir="$(cd "${base_dir}" && pwd)"

  if [ "${#base_dir}" -ne "${#head_dir}" ]; then
    echo "Warning: the base and head paths differ in length, which subjects that resolve against the working directory measure as a difference between the checkouts." >&2
  fi

  rm -rf "${base_dir}/.phpbench"

  echo "==> Measuring base: ${base_dir}"
  (cd "${base_dir}" && "${phpbench}" run --store --tag=baseline --progress=none)

  cp -R "${base_dir}/.phpbench" "${head_dir}/.phpbench"

  reference=(--ref=baseline --assert="mode(variant.time.avg) <= mode(baseline.time.avg) +/- ${threshold}%")
fi

echo "==> Measuring head: ${head_dir}"
(cd "${head_dir}" && "${phpbench}" run \
  --report=aggregate \
  ${reference[@]+"${reference[@]}"} \
  ${passthrough[@]+"${passthrough[@]}"})
