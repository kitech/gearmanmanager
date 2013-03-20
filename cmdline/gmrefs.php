<?php
/* gmrefs.php --- 
 * 
 * Author: liuguangzhao
 * Created: 2012-05-18 11:40:56 +0000
 * Version: $Id$
 */

$e= new ReflectionExtension('gearman');
foreach ($e->getClasses() as $c) {
    print '**' . $c->name . "**\n\n";
    foreach ($c->getMethods() as $m) {
        print '  * ' . $m->name . '(';
        $sep= '';
        foreach ($m->getParameters() as $p) {
            print $sep;
            $sep= ', ';
            if ($p->isOptional())
                print '//$' . $p->name . '//';
            else
                print '$' . $p->name;
        }
        print ")\n";
    }
    print "\n";
}


