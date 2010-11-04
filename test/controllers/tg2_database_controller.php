<?php namespace nmvc;

/**
 * Responsible for testing models, database api, querying, etc.
 */
class Tg2DatabaseController extends TestGroupController {
    public function _run() {
        parent::_run();
        $this->autoRun();
        $this->complete();
    }

    public function do_sync() {
        Model::syncronizeAllModels();
    }

    public function do_repair() {
        Model::repairAllModels();
        request\reset();
    }

    public function do_purify() {
        Model::purifyAllModels();
        request\reset();
    }

    public function link_unlink() {
        Test1Model::select()->unlink();
        $this->assert(Test1Model::select()->count(), 0);
        for ($i = 0; $i < 10; $i++) {
           $test_model = new Test1Model();
           $this->assert($test_model->id, 0);
           $this->assert(!$test_model->isLinked());
           $test_model->integer_f = $i;
           $test_model->store();
           $id = $test_model->id;
           $this->assert($test_model->isLinked());
           $this->assert($test_model->id > 0);
           if ($i % 2 == 0) {
               $test_model->unlink();
               $this->assert($test_model->id, 0);
               $this->assert(!$test_model->isLinked());
               $test_model->store();
               $this->assert($test_model->id > 0);
               $this->assert($test_model->id != $id);
               $this->assert($test_model->isLinked());
           }
        }
    }

    public function revert() {
        Test1Model::select()->unlink();
        $model = new Test1Model();
        $model->text_f = "foo bar?";
        $model->text3_f = "baz";
        $model->store();
        $this->assert($model->text_f, "foo bar?");
        $this->assert($model->text3_f, "baz");
        $model->text_f = "foo bar???";
        $model->text3_f = "bazbaz";
        $model->revert();
        $this->assert($model->text_f, "foo bar?");
        $this->assert($model->text3_f, "bazbaz");
        $model->unlink();
    }

    public function volatile_a() {
        Test1Model::select()->unlink();
        $model = new Test1Model(true);
        $model->store();
        $this->assert($model->id, 0);
        $this->assert(!$model->isLinked());
        $model->unlink();
        $model->store();
        $this->assert($model->isVolatile());
        $this->assert(Test1Model::select()->count(), 0);
        $model2 = new Test1Model(false);
        $model2->text_f = "xyz1";
        $model2->text3_f = "xyz2";
        $model2->store();
        $fields = $model2->getColumnNames(false);
        $this->assert(\array_key_exists("text3_f", $fields), false, $fields);
        $this->assert($model2->text3_f, "xyz2");
    }

    public function volatile_b() {
        $model2 = Test1Model::select()->first();
        $this->assert($model2->text_f, "xyz1");
        $this->assert($model2->text3_f == "");
    }

    public function write_read_a() {
        Test1Model::select()->unlink();
        for ($i = 0; $i < 20; $i++) {
            $model = new Test1Model();
            $model->integer_f = $i;
            $model->text_f = $i < 10? "foo": "bar";
            $model->store();
        }
        $this->assert(Test1Model::select()->count(), 20);
    }

    public function write_read_b() {
        $i = 0;
        foreach (Test1Model::select()->orderBy("integer_f") as $model) {
            $this->assert($model->integer_f, $i);
            $this->assert($model->text_f, $i < 10? "foo": "bar");
            $i++;
        }
    }

    public function select_by_id_a() {
        Test1Model::select()->unlink();
        for ($i = 0; $i < 5; $i++) {
            $model = new Test1Model();
            $model->store();
            $model->text_f = \sha1($model->id);
            $model->store();
        }
    }

    public function select_by_id_b() {
        $ids = Model::getDataForSelection(Test1Model::select(array("id")));
        $this->assert(Test1Model::select()->count(), 5);
        $ids = \array_reverse($ids);
        for ($c = 0; $c < 2; $c++) {
            // The first tíme selection will be uncached,
            // the second time selections should be cached.
            // (maximizing code-paths)
            $i = 0;
            foreach ($ids as $id) {
                $i++;
                $id = intval($id[0]);
                if ($i % 2)
                    $instance = Test1Model::selectByID($id);
                else
                    $instance = Test1Model::select()->where("id")->is($id)->first();
                $this->assert($instance !== null);
                $this->assert($instance->id, $id);
                $this->assert($instance->text_f, \sha1($id));
            }
        }
    }

    public function select_where_in() {
        // Ready data.
        Test1Model::select()->unlink();
        Test2Model::select()->unlink();
        $id1 = array();
        for ($i = 0; $i < 5; $i++) {
            $model = new Test1Model();
            $model->text_f = $i <= 2? "foo?": "bar!";
            $model->integer_f = $i;
            $model->store();
            $id1[$i] = $model->id;
        }
        $model = new Test2Model();
        $model->another_text_f = "foo?";
        $model->store();
        $id2_0 = $model->id;
        $model = new Test2Model();
        $model->another_text_f = "bar!";
        $model->store();
        $id2_1 = $model->id;
        $model = new Test2Model();
        $model->another_text_f = "baz!?";
        $model->store();
        $id2_2 = $model->id;
        // Direct IN to same model.
        $result = Test1Model::select()->where("text_f")->isIn(
            Test1Model::select("text_f")
        )->orderBy("id")->all();
        $this->assert(\array_keys($result), $id1);
        // Direct IN to same model with additional condition.
        $result = Test1Model::select()->where("text_f")->isIn(
            Test1Model::select("text_f")
        )->and("integer_f")->isLessThan(3)->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id1[0], $id1[1], $id1[2]), \array_keys($result));
        // Direct IN to different model.
        $result = Test1Model::select()->where("text_f")->isIn(
            Test2Model::select("another_text_f")
        )->orderBy("id")->all();
        $this->assert(\array_keys($result), $id1);
        // Parent referencing IN to different model.
        $result = Test2Model::select()->where("another_text_f")->isIn(
            Test1Model::select("text_f")->where("text_f")->is(field("<-another_text_f"))
        )->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id2_0, $id2_1));
        // Parent referencing NOT IN to different model.
        $result = Test2Model::select()->where("another_text_f")->isntIn(
            Test1Model::select("text_f")->where("text_f")->is(field("<-another_text_f"))
        )->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id2_2));
    }

    public function select_where_like() {
        // Ready data.
        Test2Model::select()->unlink();
        $model = new Test2Model();
        $model->another_text_f = "wakka wokka";
        $model->store();
        $id0 = $model->id;
        $model = new Test2Model();
        $model->another_text_f = "wikka wakka";
        $model->store();
        $id1 = $model->id;
        $model = new Test2Model();
        $model->another_text_f = "wark worka work";
        $model->store();
        $id2 = $model->id;
        // Starts With
        $result = Test2Model::select()->where("another_text_f")->isStartingWith("wa")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id0, $id2));
        // Not Starts With
        $result = Test2Model::select()->where("another_text_f")->isntStartingWith("wa")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id1));
        // Ends With
        $result = Test2Model::select()->where("another_text_f")->isEndingWith("akka")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id1));
        // Not ends With
        $result = Test2Model::select()->where("another_text_f")->isntEndingWith("akka")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id0, $id2));
        // Contains
        $result = Test2Model::select()->where("another_text_f")->isContaining("kka")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id0, $id1));
        // Not Contains
        $result = Test2Model::select()->where("another_text_f")->isntContaining("kka")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id2));
        // Like
        $result = Test2Model::select()->where("another_text_f")->isLike("%wo_ka%")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id0, $id2));
        // Not like.
        $result = Test2Model::select()->where("another_text_f")->isntLike("%wo_ka%")->orderBy("id")->all();
        $this->assert(\array_keys($result), array($id1));
    }
    
    
    
    public function integrity_a() {
        TestFooModel::select()->unlink();
        TestBarModel::select()->unlink();        
        $foo1 = new TestFooModel();
        $foo1->name = "foo1";
        $bar1 = new TestBarModel();
        $bar1->name = "bar1";
        $foo1->bar = $bar1;
        $foo1->bar2 = $bar1;
        $foo1->store();
        $bar1->store();
        $foo2 = new TestFooModel();
        $foo2->name = "foo2";
        $barbaz2 = new TestBarBazModel();
        $barbaz2->name = "barbaz2";
        $foo2->bar = $bar1;
        $foo2->bar2 = $barbaz2;
        $foo2->store();
        $barbaz2->store();
        $this->assert(TestFooModel::select()->count(), 2);
        $this->assert(TestBarModel::select()->count(), 2);
        $this->assert(TestBarBazModel::select()->count(), 1);
    }
    
    public function integrity_b() {
        $foo1 = TestFooModel::select()->where("name")->is("foo1")->first();
        $foo2 = TestFooModel::select()->where("name")->is("foo2")->first();
        $bar1 = TestBarModel::select()->where("name")->is("bar1")->first();
        $barbaz2 = TestBarModel::select()->where("name")->is("barbaz2")->first();
        // Unlinking bar1.
        $bar1->unlink();
        $this->assert($foo1->isLinked(), false);
        $this->assert(TestFooModel::select()->where("name")->is("foo1")->first(), null);
        $this->assert($foo1->bar, null);
        $this->assert($foo1->bar2, null);
        $this->assert($foo2->isLinked(), true);
        $this->assert($foo2->bar, null);
        // Unlinking barbaz2.
        $barbaz2->unlink();
        $this->assert($foo2->isLinked(), false);
        $this->assert($foo2->bar2, null);
        $this->assert(TestFooModel::select()->where("name")->is("foo2")->first(), null);
    }


    /*
    public function pointers_a() {
        Test2Model::select()->unlink();
        for ($i = 0; $i < 20; $i++) {
            $model2 = new Test2Model();
            $model2->pointer_id =
            $model2->store();
        }
        $this->assert(Test2Model::select()->count(), 20);
    }

    public function pointers_b() {

    }

    public function selections() {
        Test1Model::select()->unlink();
        for ($i = 0; $i < 30; $i++) {
           $test_model = new Test1Model();
           $test_model->integer_f = $i;
           
        }
    }*/

    public function instance_equality() {
        Test1Model::select()->unlink();
        $model = new Test1Model();
        $model->store();
        $model2 = Test1Model::select()->first();
        $this->assert($model, $model2);
        $this->assert($model->id != 0);
        $this->assert($model->id, $model2->id);
    }
}
