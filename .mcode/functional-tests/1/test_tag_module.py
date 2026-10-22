import json, os, subprocess, pytest, requests

BASE = "http://localhost:%s" % os.environ.get("PORT", "8080")
ROOT = "/l2l/workspace/magento2"
HERE = os.path.dirname(os.path.abspath(__file__))


def sql(q):
    r = subprocess.run(["mysql", "-umagento", "-pmagento", "magento", "-N", "-B", "-e", q],
                       capture_output=True, text=True, timeout=60)
    assert r.returncode == 0, r.stderr
    return [l.split("\t") for l in r.stdout.strip().splitlines() if l]


@pytest.fixture(scope="session", autouse=True)
def healthy():
    r = requests.get(BASE + "/", timeout=60)
    assert r.status_code == 200


@pytest.fixture(scope="session")
def php():
    r = subprocess.run(["php", os.path.join(HERE, "tag_runner.php")], cwd=ROOT,
                       capture_output=True, text=True, timeout=300)
    line = [l for l in r.stdout.splitlines() if l.startswith("JSON:")]
    assert line, r.stdout[-2000:] + r.stderr[-2000:]
    d = json.loads(line[0][5:])
    assert d.get("ok"), d
    return d


def cols(t):
    return {r[0]: r[1:] for r in sql(
        "SELECT column_name,column_type,is_nullable,IFNULL(column_default,'NULL'),column_key,extra,column_comment "
        "FROM information_schema.columns WHERE table_schema='magento' AND table_name='%s'" % t)}


def fks(t):
    return {(r[0], r[1], r[2], r[3]): r[4] for r in sql(
        "SELECT k.column_name,k.referenced_table_name,k.referenced_column_name,'x',c.delete_rule "
        "FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints c "
        "ON c.constraint_name=k.constraint_name AND c.constraint_schema=k.table_schema "
        "WHERE k.table_schema='magento' AND k.table_name='%s' AND k.referenced_table_name IS NOT NULL" % t)}


def idx(t):
    out = {}
    for r in sql("SELECT index_name,non_unique,column_name FROM information_schema.statistics "
                 "WHERE table_schema='magento' AND table_name='%s' ORDER BY index_name,seq_in_index" % t):
        out.setdefault((r[0], r[1]), []).append(r[2])
    return out


class TestModuleEnabled:
    def test_module_enabled(self):
        r = subprocess.run(["php", "bin/magento", "module:status", "Magento_Tag"], cwd=ROOT,
                           capture_output=True, text=True, timeout=300)
        assert r.returncode == 0
        assert "Module is enabled" in r.stdout


class TestSchema:
    def test_tag_columns(self):
        c = cols("tag")
        assert set(c) == {"tag_id", "name", "status", "first_customer_id", "first_store_id"}
        assert c["tag_id"][0].startswith("int") and "unsigned" in c["tag_id"][0] and "auto_increment" in c["tag_id"][4]
        assert c["name"][0] == "varchar(255)" and c["name"][1] == "YES"
        assert c["status"][0].startswith("smallint") and c["status"][1] == "NO" and c["status"][2] == "0"
        assert c["first_customer_id"][1] == "YES" and "unsigned" in c["first_customer_id"][0]
        assert c["first_store_id"][1] == "YES" and "unsigned" in c["first_store_id"][0]

    def test_tag_fks(self):
        f = fks("tag")
        assert {k[:3]: v for k, v in f.items()} == {
            ("first_customer_id", "customer_entity", "entity_id"): "SET NULL",
            ("first_store_id", "store", "store_id"): "SET NULL"}

    def test_relation_columns(self):
        c = cols("tag_relation")
        assert set(c) == {"tag_relation_id", "tag_id", "customer_id", "product_id", "store_id", "active", "created_at"}
        assert "auto_increment" in c["tag_relation_id"][4]
        assert c["tag_id"][2] == "0" and c["product_id"][2] == "0"
        assert c["store_id"][2] == "1" and c["active"][2] == "1"
        assert c["customer_id"][1] == "YES"
        assert c["created_at"][0].startswith("timestamp")

    def test_relation_fks_and_indexes(self):
        f = fks("tag_relation")
        assert {k[:3]: v for k, v in f.items()} == {
            ("customer_id", "customer_entity", "entity_id"): "CASCADE",
            ("product_id", "catalog_product_entity", "entity_id"): "CASCADE",
            ("store_id", "store", "store_id"): "CASCADE",
            ("tag_id", "tag", "tag_id"): "CASCADE"}
        i = idx("tag_relation")
        assert ["tag_id", "customer_id", "product_id", "store_id"] in [v for (n, nu), v in i.items() if nu == "0" and n != "PRIMARY"]
        assert i[("PRIMARY", "0")] == ["tag_relation_id"]
        singles = {v[0] for (n, nu), v in i.items() if nu == "1" and len(v) == 1}
        assert {"product_id", "tag_id", "customer_id", "store_id"} <= singles

    @pytest.mark.parametrize("t,extra", [("tag_summary", ["customers", "products", "uses", "historical_uses", "popularity", "base_popularity"]),
                                         ("tag_properties", ["base_popularity"])])
    def test_summary_properties(self, t, extra):
        c = cols(t)
        assert set(c) == {"tag_id", "store_id"} | set(extra)
        for k in extra:
            assert c[k][1] == "NO" and c[k][2] == "0" and "unsigned" in c[k][0]
        i = idx(t)
        assert i[("PRIMARY", "0")] == ["tag_id", "store_id"]
        assert {k[:3]: v for k, v in fks(t).items()} == {
            ("store_id", "store", "store_id"): "CASCADE", ("tag_id", "tag", "tag_id"): "CASCADE"}
        assert any(v == ["store_id"] for (n, nu), v in i.items() if n != "PRIMARY")


class TestHttp:
    def test_tag_route_404_safe(self):
        r = requests.get(BASE + "/index.php/tag/", timeout=60)
        assert r.status_code == 404

    def test_tag_unknown_action_404(self):
        r = requests.get(BASE + "/index.php/tag/customer/view/tagId/1", timeout=60)
        assert r.status_code == 404

    def test_home_still_ok(self):
        assert requests.get(BASE + "/", timeout=60).status_code == 200


class TestModels:
    def test_save_and_load_by_name(self, php):
        assert php["saved_id"] > 0 and php["load_by_name_id"] == php["saved_id"]
        assert php["load_by_name_name"].startswith("ftrun_")

    def test_load_by_name_missing(self, php):
        assert not php["load_missing_id"]

    def test_constants(self, php):
        assert php["status_consts"] == [-1, 0, 1, "success", "new", "exist", "rejected", 1, 0]
        assert php["status_getters"] == [1, 0, -1]

    def test_relation_save_reload(self, php):
        assert php["relation_id"] > 0
        assert php["relation_reload"] == [php["saved_id"], php["relation_reload"][1], 1, 1]
        assert len(php["relation_product_ids"]) == 1

    def test_aggregate_writes_global_summary(self, php):
        g = php["summary_global"]
        assert g and int(g["tag_id"]) == php["saved_id"]

    def test_helper_data(self, php):
        assert php["helper_statuses"] == [-1, 0, 1]
        assert php["helper_options"] == 3
        assert list(php["helper_extract"].values()) == ["foo", "bar baz", "qux"]

    def test_session(self, php):
        assert php["session_class"].startswith("Magento\\Tag\\Model\\Session")
        assert php["session_val"] == "v"

    def test_delete_cascades(self, php):
        assert php["after_delete"] == [0, 0]
